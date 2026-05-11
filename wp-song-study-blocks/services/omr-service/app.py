import base64
import logging
import os
import re
import shlex
import shutil
import subprocess
import tempfile
import time
import zipfile
from dataclasses import dataclass, field
from functools import lru_cache
from pathlib import Path
from typing import Any

from fastapi import FastAPI, Header
from fastapi.responses import JSONResponse
from PIL import Image, ImageOps, UnidentifiedImageError
from pydantic import BaseModel, Field


SERVICE_VERSION = "1.0.0"
API_KEY = os.environ.get("OMR_API_KEY", "").strip()
DEFAULT_ENGINE = os.environ.get("OMR_ENGINE", "auto").strip().lower() or "auto"
AUDIVERIS_BIN = os.environ.get("AUDIVERIS_BIN", "audiveris").strip() or "audiveris"
HOMR_BIN = os.environ.get("HOMR_BIN", "homr").strip() or "homr"
TIMEOUT_SECONDS = int(os.environ.get("OMR_TIMEOUT_SECONDS", "180"))
MAX_IMAGE_BYTES = int(os.environ.get("OMR_MAX_IMAGE_BYTES", str(16 * 1024 * 1024)))
NORMALIZE_IMAGES = os.environ.get("OMR_NORMALIZE_IMAGES", "true").strip().lower() not in {"0", "false", "no"}
KEEP_WORKDIR = os.environ.get("OMR_KEEP_WORKDIR", "false").strip().lower() in {"1", "true", "yes"}

ALLOWED_MIME_TYPES = {
    "image/png": ".png",
    "image/jpeg": ".jpg",
    "image/jpg": ".jpg",
    "image/tiff": ".tif",
    "image/bmp": ".bmp",
    "application/pdf": ".pdf",
}
ALLOWED_EXTENSIONS = {".png", ".jpg", ".jpeg", ".tif", ".tiff", ".bmp", ".pdf"}

logging.basicConfig(
    level=os.environ.get("LOG_LEVEL", "INFO").upper(),
    format="%(asctime)s %(levelname)s %(name)s %(message)s",
)
logger = logging.getLogger("harmonyatlas.omr")


class ScoreHints(BaseModel):
    tempo: int = Field(default=100, ge=40, le=240)
    instrument: str = "piano"
    notes: str = ""


class OmrRequest(BaseModel):
    song_id: int | str | None = None
    attachment_id: str = ""
    file_name: str = "score.png"
    mime_type: str = "image/png"
    image_base64: str
    score: ScoreHints = Field(default_factory=ScoreHints)
    engine: str | None = None


@dataclass
class OmrRunResult:
    engine: str
    musicxml: str
    musicxml_file: Path
    command: list[str]
    stdout: str = ""
    stderr: str = ""
    duration_seconds: float = 0.0
    warnings: list[str] = field(default_factory=list)


class OmrServiceError(Exception):
    def __init__(self, code: str, message: str, details: str = "", status_code: int = 500) -> None:
        super().__init__(message)
        self.code = code
        self.message = message
        self.details = details
        self.status_code = status_code


app = FastAPI(title="HarmonyAtlas OMR Service", version=SERVICE_VERSION)


def sanitize_file_name(file_name: str) -> str:
    name = Path(file_name or "score.png").name
    name = re.sub(r"[^A-Za-z0-9._-]+", "_", name).strip("._")
    return name or "score.png"


def resolve_engine(engine: str | None = None) -> str:
    selected = (engine or DEFAULT_ENGINE or "auto").strip().lower()
    if selected == "auto":
        return "audiveris"
    if selected in {"audiveris", "homr"}:
        return selected
    raise OmrServiceError("UNSUPPORTED_ENGINE", f"Unsupported OMR engine: {selected}", status_code=400)


def error_response(
    status_code: int,
    code: str,
    message: str,
    *,
    details: str = "",
    engine: str | None = None,
    song_id: int | str | None = None,
    attachment_id: str = "",
    warnings: list[str] | None = None,
) -> JSONResponse:
    return JSONResponse(
        status_code=status_code,
        content={
            "ok": False,
            "engine": engine or resolve_engine_safely(),
            "song_id": song_id,
            "attachment_id": attachment_id,
            "message": message,
            "error": {
                "code": code,
                "message": message,
                "details": details,
            },
            "warnings": warnings or [],
        },
    )


def resolve_engine_safely() -> str:
    try:
        return resolve_engine()
    except OmrServiceError:
        return DEFAULT_ENGINE or "auto"


def require_api_key(authorization: str | None, x_wpss_omr_key: str | None) -> JSONResponse | None:
    if not API_KEY:
        return None
    bearer = ""
    if authorization and authorization.lower().startswith("bearer "):
        bearer = authorization[7:].strip()
    candidate = bearer or (x_wpss_omr_key or "").strip()
    if candidate != API_KEY:
        return error_response(401, "UNAUTHORIZED", "Invalid OMR API key")
    return None


def validate_mime_type(mime_type: str) -> str:
    normalized = (mime_type or "").split(";")[0].strip().lower()
    if normalized not in ALLOWED_MIME_TYPES:
        raise OmrServiceError(
            "UNSUPPORTED_MIME_TYPE",
            f"Unsupported mime_type: {mime_type}",
            "Allowed: " + ", ".join(sorted(ALLOWED_MIME_TYPES)),
            status_code=415,
        )
    return normalized


def extension_for_request(payload: OmrRequest, mime_type: str) -> str:
    suffix = Path(sanitize_file_name(payload.file_name)).suffix.lower()
    if suffix in ALLOWED_EXTENSIONS:
        return ".jpg" if suffix == ".jpeg" else suffix
    return ALLOWED_MIME_TYPES[mime_type]


def decode_image(payload: OmrRequest) -> bytes:
    encoded = payload.image_base64 or ""
    max_base64_chars = int(MAX_IMAGE_BYTES * 1.4) + 8
    if len(encoded) > max_base64_chars:
        raise OmrServiceError("IMAGE_TOO_LARGE", "Encoded image is too large", status_code=413)
    try:
        data = base64.b64decode(encoded, validate=True)
    except Exception as exc:
        raise OmrServiceError("INVALID_BASE64", "Invalid image_base64", str(exc), status_code=400) from exc
    if not data:
        raise OmrServiceError("EMPTY_IMAGE", "Empty image", status_code=400)
    if len(data) > MAX_IMAGE_BYTES:
        raise OmrServiceError(
            "IMAGE_TOO_LARGE",
            f"Image exceeds {MAX_IMAGE_BYTES} bytes",
            f"Received {len(data)} bytes",
            status_code=413,
        )
    return data


def normalize_image_for_omr(input_path: Path, workdir: Path, mime_type: str) -> tuple[Path, list[str]]:
    warnings: list[str] = []
    if not NORMALIZE_IMAGES or mime_type == "application/pdf":
        return input_path, warnings

    normalized_path = workdir / "normalized.png"
    try:
        with Image.open(input_path) as image:
            image.load()
            grayscale = ImageOps.grayscale(image)
            enhanced = ImageOps.autocontrast(grayscale)
            enhanced.save(normalized_path, format="PNG", optimize=True)
            logger.info(
                "normalized image for OMR original=%s normalized=%s size=%sx%s",
                input_path.name,
                normalized_path.name,
                enhanced.width,
                enhanced.height,
            )
            return normalized_path, warnings
    except UnidentifiedImageError as exc:
        raise OmrServiceError("INVALID_IMAGE", "Image could not be opened", str(exc), status_code=400) from exc
    except Exception as exc:
        warnings.append(f"Image normalization skipped: {exc}")
        logger.warning("image normalization failed path=%s error=%s", input_path.name, exc)
        return input_path, warnings


def first_musicxml_from_mxl(path: Path) -> str:
    with zipfile.ZipFile(path) as archive:
        candidates = [
            name
            for name in archive.namelist()
            if name.lower().endswith((".xml", ".musicxml")) and not name.startswith("META-INF/")
        ]
        if not candidates:
            raise OmrServiceError("NO_MUSICXML", "MXL output did not contain MusicXML")
        return archive.read(candidates[0]).decode("utf-8", errors="replace")


def looks_like_musicxml(content: str) -> bool:
    head = content[:4096].lower()
    return "<score-partwise" in head or "<score-timewise" in head


def find_musicxml(output_dir: Path) -> tuple[str, Path]:
    logger.info("searching MusicXML output_dir=%s", output_dir)
    candidates = sorted(output_dir.rglob("*.musicxml")) + sorted(output_dir.rglob("*.xml"))
    for xml_file in candidates:
        content = xml_file.read_text(encoding="utf-8", errors="replace")
        if looks_like_musicxml(content):
            logger.info("found MusicXML file=%s bytes=%s", xml_file, len(content.encode("utf-8", errors="replace")))
            return content, xml_file

    mxl_files = sorted(output_dir.rglob("*.mxl"))
    for mxl_file in mxl_files:
        content = first_musicxml_from_mxl(mxl_file)
        if looks_like_musicxml(content):
            logger.info("found MXL file=%s musicxml_bytes=%s", mxl_file, len(content.encode("utf-8", errors="replace")))
            return content, mxl_file

    logger.error("no MusicXML output found output_dir=%s files=%s", output_dir, [str(path) for path in output_dir.rglob("*")])
    raise OmrServiceError("NO_MUSICXML", "No MusicXML output was produced")


def run_command(command: list[str], *, cwd: Path | None = None) -> subprocess.CompletedProcess[str]:
    logger.info("running OMR command=%s cwd=%s timeout=%ss", shlex.join(command), cwd or ".", TIMEOUT_SECONDS)
    started = time.monotonic()
    try:
        completed = subprocess.run(
            command,
            check=False,
            timeout=TIMEOUT_SECONDS,
            capture_output=True,
            text=True,
            cwd=cwd,
        )
    except subprocess.TimeoutExpired as exc:
        duration = time.monotonic() - started
        logger.error("OMR command timed out after %.2fs command=%s", duration, shlex.join(command))
        raise OmrServiceError(
            "AUDIVERIS_TIMEOUT",
            "OMR engine timed out",
            str(exc),
            status_code=504,
        ) from exc

    duration = time.monotonic() - started
    logger.info("OMR command finished returncode=%s duration=%.2fs", completed.returncode, duration)
    if completed.returncode != 0:
        detail = "\n".join(part for part in [completed.stderr.strip(), completed.stdout.strip()] if part)
        logger.error("OMR command failed returncode=%s details=%s", completed.returncode, detail[:2000])
        raise OmrServiceError(
            "AUDIVERIS_FAILED",
            "OMR engine failed",
            detail[:4000],
            status_code=422,
        )
    return completed


def run_audiveris(input_path: Path, workdir: Path) -> OmrRunResult:
    executable = shutil.which(AUDIVERIS_BIN)
    if not executable:
        raise OmrServiceError(
            "AUDIVERIS_NOT_AVAILABLE",
            f"Audiveris executable not found: {AUDIVERIS_BIN}",
            status_code=503,
        )

    output_dir = workdir / "audiveris-output"
    output_dir.mkdir(parents=True, exist_ok=True)
    logger.info("starting Audiveris input=%s output_dir=%s", input_path, output_dir)
    command = [
        executable,
        "-batch",
        "-transcribe",
        "-export",
        "-output",
        str(output_dir),
        "--",
        str(input_path),
    ]
    started = time.monotonic()
    completed = run_command(command)
    musicxml, musicxml_file = find_musicxml(output_dir)
    return OmrRunResult(
        engine="audiveris",
        musicxml=musicxml,
        musicxml_file=musicxml_file,
        command=command,
        stdout=completed.stdout[-4000:],
        stderr=completed.stderr[-4000:],
        duration_seconds=time.monotonic() - started,
    )


def run_homr(input_path: Path, workdir: Path) -> OmrRunResult:
    if not shutil.which(HOMR_BIN):
        raise OmrServiceError("HOMR_NOT_AVAILABLE", "homr executable is not installed", status_code=501)
    raise OmrServiceError("HOMR_NOT_IMPLEMENTED", "homr engine is reserved for a future adapter", status_code=501)


def run_omr(engine: str, input_path: Path, workdir: Path) -> OmrRunResult:
    resolved = resolve_engine(engine)
    if resolved == "audiveris":
        return run_audiveris(input_path, workdir)
    if resolved == "homr":
        return run_homr(input_path, workdir)
    raise OmrServiceError("UNSUPPORTED_ENGINE", f"Unsupported OMR engine: {resolved}", status_code=400)


@lru_cache(maxsize=8)
def command_version(command: str) -> str | None:
    executable = shutil.which(command)
    if not executable:
        return None

    saw_usable_audiveris_output = False
    for args in ([executable, "--version"], [executable, "-version"], [executable, "-help"]):
        try:
            completed = subprocess.run(args, check=False, timeout=8, capture_output=True, text=True)
        except Exception:
            continue
        output = (completed.stdout + "\n" + completed.stderr).strip()
        lowered = output.lower()
        if any(
            marker in lowered
            for marker in (
                "could not open '/lib64/ld-linux-x86-64.so.2'",
                "exec format error",
                "qemu-x86_64",
                "bad cpu type",
                "unsupported architecture",
            )
        ):
            logger.warning("command probe failed command=%s output=%s", executable, output[:500])
            return None
        if "not a valid option" in output or "Error in command line" in output:
            continue
        if Path(command).name.lower() == "audiveris" and ("audiveris" in lowered or "usage:" in lowered):
            saw_usable_audiveris_output = True
        match = re.search(r"(?:Audiveris|homr)[^\d]*(\d+(?:\.\d+)+)", output, re.IGNORECASE)
        if match:
            return match.group(1)
        if completed.returncode == 0 and output and args[-1] in {"--version", "-version"}:
            return output.splitlines()[0][:120]
    if Path(command).name.lower() == "audiveris" and saw_usable_audiveris_output:
        return os.environ.get("AUDIVERIS_VERSION", "").strip() or None
    return None


def engine_health() -> dict[str, Any]:
    audiveris_binary = shutil.which(AUDIVERIS_BIN)
    audiveris_version = command_version(AUDIVERIS_BIN) if audiveris_binary else None
    audiveris_available = bool(audiveris_binary and audiveris_version)
    homr_available = bool(shutil.which(HOMR_BIN))
    return {
        "audiveris": {
            "available": audiveris_available,
            "version": audiveris_version,
            "binary": audiveris_binary,
        },
        "homr": {
            "available": homr_available,
            "version": command_version(HOMR_BIN) if homr_available else None,
            "binary": shutil.which(HOMR_BIN),
            "implemented": False,
        },
    }


def service_info_payload() -> dict[str, Any]:
    engines = engine_health()
    selected = resolve_engine_safely()
    selected_info = engines.get(selected, {})
    selected_available = bool(selected_info.get("available")) and selected_info.get("implemented", True) is not False
    return {
        "ok": selected_available,
        "service": "omr-service",
        "version": SERVICE_VERSION,
        "engine": selected,
        "engines": engines,
        "limits": {
            "max_image_bytes": MAX_IMAGE_BYTES,
            "timeout_seconds": TIMEOUT_SECONDS,
            "normalize_images": NORMALIZE_IMAGES,
        },
        "allowed_mime_types": sorted(ALLOWED_MIME_TYPES),
        "api_key_configured": bool(API_KEY),
    }


@app.get("/health")
def health() -> dict[str, Any]:
    return service_info_payload()


@app.get("/info")
def info() -> dict[str, Any]:
    return service_info_payload()


@app.post("/omr", response_model=None)
def interpret_score(
    payload: OmrRequest,
    authorization: str | None = Header(default=None),
    x_wpss_omr_key: str | None = Header(default=None),
) -> dict[str, Any] | JSONResponse:
    auth_error = require_api_key(authorization, x_wpss_omr_key)
    if auth_error:
        return auth_error

    request_started = time.monotonic()
    warnings: list[str] = []
    workdir_path: Path | None = None

    try:
        engine = resolve_engine(payload.engine)
        mime_type = validate_mime_type(payload.mime_type)
        image = decode_image(payload)
        file_name = sanitize_file_name(payload.file_name)
        extension = extension_for_request(payload, mime_type)
        workdir_path = Path(tempfile.mkdtemp(prefix="harmonyatlas-omr-"))
        input_path = workdir_path / f"input{extension}"
        input_path.write_bytes(image)
        logger.info("wrote OMR input file path=%s bytes=%s", input_path, input_path.stat().st_size)

        logger.info(
            "received OMR request song_id=%s attachment_id=%s engine=%s mime_type=%s file_name=%s bytes=%s workdir=%s",
            payload.song_id,
            payload.attachment_id,
            engine,
            mime_type,
            file_name,
            len(image),
            workdir_path,
        )

        omr_input_path, normalize_warnings = normalize_image_for_omr(input_path, workdir_path, mime_type)
        warnings.extend(normalize_warnings)
        result = run_omr(engine, omr_input_path, workdir_path)
        warnings.extend(result.warnings)
        duration = time.monotonic() - request_started

        logger.info(
            "OMR request completed song_id=%s attachment_id=%s engine=%s duration=%.2fs musicxml_bytes=%s",
            payload.song_id,
            payload.attachment_id,
            result.engine,
            duration,
            len(result.musicxml.encode("utf-8", errors="replace")),
        )

        return {
            "ok": True,
            "status": "ready",
            "engine": result.engine,
            "song_id": payload.song_id,
            "attachment_id": payload.attachment_id,
            "message": "Partitura interpretada por OMR.",
            "tempo": payload.score.tempo,
            "instrument": payload.score.instrument,
            "musicxml": result.musicxml,
            "warnings": warnings,
            "artifacts": {
                "workdir": str(workdir_path) if KEEP_WORKDIR else None,
                "input_file": input_path.name,
                "normalized_file": omr_input_path.name if omr_input_path != input_path else None,
                "musicxml_file": str(result.musicxml_file.relative_to(workdir_path))
                if result.musicxml_file.is_relative_to(workdir_path)
                else result.musicxml_file.name,
            },
            "diagnostics": {
                "duration_seconds": round(duration, 3),
                "command": shlex.join(result.command),
            },
        }
    except OmrServiceError as exc:
        logger.error(
            "OMR request failed song_id=%s attachment_id=%s code=%s message=%s details=%s",
            payload.song_id,
            payload.attachment_id,
            exc.code,
            exc.message,
            exc.details[:2000],
        )
        return error_response(
            exc.status_code,
            exc.code,
            exc.message,
            details=exc.details,
            engine=resolve_engine_safely(),
            song_id=payload.song_id,
            attachment_id=payload.attachment_id,
            warnings=warnings,
        )
    except Exception as exc:
        logger.exception("unexpected OMR error song_id=%s attachment_id=%s", payload.song_id, payload.attachment_id)
        return error_response(
            500,
            "UNEXPECTED_ERROR",
            "Unexpected OMR service error",
            details=str(exc),
            engine=resolve_engine_safely(),
            song_id=payload.song_id,
            attachment_id=payload.attachment_id,
            warnings=warnings,
        )
    finally:
        if workdir_path and workdir_path.exists() and not KEEP_WORKDIR:
            shutil.rmtree(workdir_path, ignore_errors=True)
