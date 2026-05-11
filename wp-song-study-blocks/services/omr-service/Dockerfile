ARG OMR_IMAGE_PLATFORM=linux/amd64
FROM --platform=${OMR_IMAGE_PLATFORM} ubuntu:24.04

ARG AUDIVERIS_VERSION=5.10.2

ENV DEBIAN_FRONTEND=noninteractive \
    PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    AUDIVERIS_VERSION=${AUDIVERIS_VERSION} \
    AUDIVERIS_ARCH=x86_64 \
    OMR_ENGINE=auto \
    AUDIVERIS_BIN=audiveris \
    OMR_TIMEOUT_SECONDS=180 \
    OMR_MAX_IMAGE_BYTES=16777216 \
    JAVA_TOOL_OPTIONS=-Djava.awt.headless=true

WORKDIR /app

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        default-jre-headless \
        file \
        fontconfig \
        python3 \
        python3-pip \
        python3-venv \
        tesseract-ocr \
    && rm -rf /var/lib/apt/lists/*

COPY install_audiveris.py /tmp/install_audiveris.py

RUN python3 /tmp/install_audiveris.py /tmp/audiveris.deb \
    && apt-get update \
    && apt-get install -y --no-install-recommends libasound2t64 libxi6 libxtst6 \
    && dpkg-deb -x /tmp/audiveris.deb / \
    && rm -f /tmp/audiveris.deb /tmp/install_audiveris.py \
    && rm -rf /var/lib/apt/lists/* \
    && AUDIVERIS_PATH="$(find /opt /usr -type f \( -iname 'Audiveris' -o -iname 'audiveris' \) -perm /111 2>/dev/null | head -n 1)" \
    && test -n "$AUDIVERIS_PATH" \
    && ln -sf "$AUDIVERIS_PATH" /usr/local/bin/audiveris \
    && ( /usr/local/bin/audiveris -batch -help >/tmp/audiveris-help.txt 2>&1 || test -s /tmp/audiveris-help.txt )

COPY requirements.txt .
RUN python3 -m venv /opt/omr-venv \
    && /opt/omr-venv/bin/pip install --no-cache-dir --upgrade pip \
    && /opt/omr-venv/bin/pip install --no-cache-dir -r requirements.txt

COPY app.py .

EXPOSE 8080

# Audiveris is invoked by app.py as:
# audiveris -batch -transcribe -export -output <workdir>/audiveris-output -- <input-file>
CMD ["/opt/omr-venv/bin/uvicorn", "app:app", "--host", "0.0.0.0", "--port", "8080"]
