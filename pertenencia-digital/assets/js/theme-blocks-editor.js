(function (wp) {
  if (!wp || !wp.blocks || !wp.element) {
    return;
  }

  const { registerBlockType, getBlockType } = wp.blocks;
  const { createElement: el, Fragment, useEffect, useState } = wp.element;
  const { __ } = wp.i18n || { __: (value) => value };
  const ServerSideRender = wp.serverSideRender;
  const { InspectorControls, useBlockProps } = wp.blockEditor || {};
  const { createHigherOrderComponent } = wp.compose || {};
  const { addFilter } = wp.hooks || {};
  const {
    BaseControl,
    Button,
    ColorPalette,
    Disabled,
    PanelBody,
    TextControl,
    ToggleControl,
    SelectControl,
    RangeControl,
  } = wp.components || {};
  const apiFetch = wp.apiFetch;
  const palette =
    wp.data && typeof wp.data.select === "function"
      ? (wp.data.select("core/block-editor")?.getSettings?.().colors || [])
      : [];
  const editorialShellThemeSettings = window.pdEditorialShellThemeSettings || {
    canManage: false,
    settings: {},
  };
  const editorialShellOptionMap = {
    shellBackground: "pd_editorial_shell_background",
    landingBackground: "pd_editorial_shell_landing_background",
    accent: "pd_editorial_shell_accent",
    accentMusic: "pd_editorial_shell_accent_music",
    accentTechnology: "pd_editorial_shell_accent_technology",
    accentLegal: "pd_editorial_shell_accent_legal",
    heroBackground: "pd_editorial_hero_background",
    heroBorder: "pd_editorial_hero_border",
    heroText: "pd_editorial_hero_text",
    heroTitle: "pd_editorial_hero_title",
    surfaceBackground: "pd_editorial_surface_background",
    surfaceBorder: "pd_editorial_surface_border",
    surfaceText: "pd_editorial_surface_text",
    surfaceHeading: "pd_editorial_surface_heading",
  };
  const editorialShellFieldGroups = [
    {
      title: __("Shell", "pertenencia-digital"),
      fields: [
        {
          key: "shellBackground",
          label: __("Fondo global del shell", "pertenencia-digital"),
          type: "background",
        },
        {
          key: "landingBackground",
          label: __("Fondo del shell landing", "pertenencia-digital"),
          type: "background",
        },
        {
          key: "accent",
          label: __("Acento editorial base", "pertenencia-digital"),
          type: "color",
        },
        {
          key: "accentMusic",
          label: __("Acento de musica", "pertenencia-digital"),
          type: "color",
        },
        {
          key: "accentTechnology",
          label: __("Acento de tecnologia", "pertenencia-digital"),
          type: "color",
        },
        {
          key: "accentLegal",
          label: __("Acento legal", "pertenencia-digital"),
          type: "color",
        },
      ],
    },
    {
      title: __("Hero", "pertenencia-digital"),
      fields: [
        {
          key: "heroBackground",
          label: __("Fondo global del hero", "pertenencia-digital"),
          type: "background",
        },
        {
          key: "heroBorder",
          label: __("Borde global del hero", "pertenencia-digital"),
          type: "color",
        },
        {
          key: "heroText",
          label: __("Texto global del hero", "pertenencia-digital"),
          type: "color",
        },
        {
          key: "heroTitle",
          label: __("Titulos globales del hero", "pertenencia-digital"),
          type: "color",
        },
      ],
    },
    {
      title: __("Surface", "pertenencia-digital"),
      fields: [
        {
          key: "surfaceBackground",
          label: __("Fondo global de surface", "pertenencia-digital"),
          type: "background",
        },
        {
          key: "surfaceBorder",
          label: __("Borde global de surface", "pertenencia-digital"),
          type: "color",
        },
        {
          key: "surfaceText",
          label: __("Texto global de surface", "pertenencia-digital"),
          type: "color",
        },
        {
          key: "surfaceHeading",
          label: __("Titulos globales de surface", "pertenencia-digital"),
          type: "color",
        },
      ],
    },
  ];

  const renderServerPreview = (blockName, props) => {
    const blockProps =
      typeof useBlockProps === "function"
        ? useBlockProps({ className: "pd-theme-block-preview" })
        : { className: "pd-theme-block-preview" };

    if (ServerSideRender) {
      return el(
        "div",
        blockProps,
        Disabled
          ? el(
              Disabled,
              {},
              el(ServerSideRender, {
                block: blockName,
                attributes: props.attributes,
              })
            )
          : el(ServerSideRender, {
              block: blockName,
              attributes: props.attributes,
            })
      );
    }

    return el(
      "div",
      blockProps,
      el(
        "p",
        {},
        __("La vista previa del bloque no esta disponible en este editor.", "pertenencia-digital")
      )
    );
  };

  const renderTextControl = (props, attribute, label, help) =>
    el(TextControl, {
      label,
      help,
      value: props.attributes[attribute] || "",
      onChange: (value) => props.setAttributes({ [attribute]: value }),
    });

  const renderToggleControl = (props, attribute, label, help) =>
    el(ToggleControl, {
      label,
      help,
      checked: !!props.attributes[attribute],
      onChange: (value) => props.setAttributes({ [attribute]: value }),
    });

  const renderSelectControl = (props, attribute, label, options, help) =>
    el(SelectControl, {
      label,
      help,
      value: props.attributes[attribute],
      options,
      onChange: (value) => props.setAttributes({ [attribute]: value }),
    });

  const renderColorControl = (props, attribute, label) =>
    BaseControl && ColorPalette
      ? el(
          BaseControl,
          { label },
          el(ColorPalette, {
            colors: palette,
            value: props.attributes[attribute] || "",
            onChange: (value) => props.setAttributes({ [attribute]: value || "" }),
            clearable: true,
            enableAlpha: true,
          })
        )
      : renderTextControl(props, attribute, label);

  const renderResetButton = (props, attributes, label) =>
    Button
      ? el(
          Button,
          {
            variant: "secondary",
            onClick: () =>
              props.setAttributes(
                attributes.reduce((next, attribute) => {
                  next[attribute] = "";
                  return next;
                }, {})
              ),
          },
          label || __("Restablecer a defaults del tema", "pertenencia-digital")
        )
      : null;

  const renderResetNumericButton = (props, values, label) =>
    Button
      ? el(
          Button,
          {
            variant: "secondary",
            onClick: () => props.setAttributes(values),
          },
          label || __("Restablecer tamano por defecto", "pertenencia-digital")
        )
      : null;

  const renderSettingsColorControl = (values, setValues, attribute, label) =>
    BaseControl && ColorPalette
      ? el(
          BaseControl,
          { label },
          el(ColorPalette, {
            colors: palette,
            value: values[attribute] || "",
            onChange: (value) => setValues({ ...values, [attribute]: value || "" }),
            clearable: true,
            enableAlpha: true,
          })
        )
      : el(TextControl, {
          label,
          value: values[attribute] || "",
          onChange: (value) => setValues({ ...values, [attribute]: value || "" }),
        });

  const renderSettingsBackgroundControl = (values, setValues, attribute, label) =>
    el(TextControl, {
      label,
      help: __(
        "Acepta color, gradiente o cualquier valor CSS valido para background.",
        "pertenencia-digital"
      ),
      value: values[attribute] || "",
      onChange: (value) => setValues({ ...values, [attribute]: value || "" }),
    });

  const createEditorNotice = (type, message) => {
    const notices =
      wp.data && typeof wp.data.dispatch === "function"
        ? wp.data.dispatch("core/notices")
        : null;

    if (!notices) {
      return;
    }

    if ("error" === type && typeof notices.createErrorNotice === "function") {
      notices.createErrorNotice(message, { type: "snackbar" });
      return;
    }

    if ("success" === type && typeof notices.createSuccessNotice === "function") {
      notices.createSuccessNotice(message, { type: "snackbar" });
    }
  };

  if (addFilter && createHigherOrderComponent) {
    const withEditorialShellThemeInspector = createHigherOrderComponent(
      (BlockEdit) =>
        function EditorialShellThemeInspector(props) {
          if ("core/group" !== props.name) {
            return el(BlockEdit, props);
          }

          const className = props.attributes?.className || "";
          const classTokens = className.split(/\s+/).filter(Boolean);
          const isEditorialShell = classTokens.includes("pd-editorial-shell");
          const isEditorialHero = classTokens.includes("pd-editorial-hero");
          const isEditorialSurface = classTokens.includes("pd-editorial-surface");

          if (!isEditorialShell && !isEditorialHero && !isEditorialSurface) {
            return el(BlockEdit, props);
          }

          const [globalValues, setGlobalValues] = useState({
            ...(editorialShellThemeSettings.settings || {}),
          });
          const [isSaving, setIsSaving] = useState(false);

          useEffect(() => {
            setGlobalValues({ ...(window.pdEditorialShellThemeSettings?.settings || {}) });
          }, [props.clientId]);

          const saveGlobalValues = (nextValues) => {
            if (!apiFetch || !editorialShellThemeSettings.canManage) {
              return;
            }

            const payload = Object.entries(editorialShellOptionMap).reduce((next, [key, option]) => {
              next[option] = nextValues[key] || "";
              return next;
            }, {});

            setIsSaving(true);

            apiFetch({
              path: "/wp/v2/settings",
              method: "POST",
              data: payload,
            })
              .then((response) => {
                const savedValues = Object.entries(editorialShellOptionMap).reduce((next, [key, option]) => {
                  next[key] = response?.[option] || "";
                  return next;
                }, {});

                window.pdEditorialShellThemeSettings = {
                  ...(window.pdEditorialShellThemeSettings || {}),
                  settings: savedValues,
                };
                setGlobalValues(savedValues);
                createEditorNotice(
                  "success",
                  __("La tematizacion global editorial fue actualizada.", "pertenencia-digital")
                );
              })
              .catch(() => {
                createEditorNotice(
                  "error",
                  __("No fue posible guardar la tematizacion global editorial.", "pertenencia-digital")
                );
              })
              .finally(() => {
                setIsSaving(false);
              });
          };

          return el(
            Fragment,
            {},
            el(BlockEdit, props),
            InspectorControls
              ? el(
                  InspectorControls,
                  {},
                  el(
                    PanelBody,
                    {
                      title: __("Shell y rectangulos globales", "pertenencia-digital"),
                      initialOpen: false,
                    },
                    el(
                      "p",
                      {},
                      __(
                        "Estos valores se aplican globalmente a shell, hero y surface. Si un bloque ya tiene fondo propio, ese bloque conserva su override local.",
                        "pertenencia-digital"
                      )
                    ),
                    editorialShellThemeSettings.canManage
                      ? editorialShellFieldGroups.map((group) =>
                          el(
                            "div",
                            { key: group.title, className: "pd-theme-shell-settings-group" },
                            el("h4", {}, group.title),
                            group.fields.map((field) =>
                              el(
                                Fragment,
                                { key: field.key },
                                "background" === field.type
                                  ? renderSettingsBackgroundControl(globalValues, setGlobalValues, field.key, field.label)
                                  : renderSettingsColorControl(globalValues, setGlobalValues, field.key, field.label)
                              )
                            )
                          )
                        )
                      : el(
                          "p",
                          {},
                          __(
                            "Necesitas permisos de administrador para cambiar estos colores globales.",
                            "pertenencia-digital"
                          )
                        ),
                    editorialShellThemeSettings.canManage && Button
                      ? el(
                          "div",
                          { className: "pd-theme-shell-settings-actions" },
                          el(
                            Button,
                            {
                              variant: "primary",
                              onClick: () => saveGlobalValues(globalValues),
                              isBusy: isSaving,
                              disabled: isSaving,
                            },
                            __("Guardar tematizacion global", "pertenencia-digital")
                          ),
                          el(
                            Button,
                            {
                              variant: "secondary",
                              onClick: () =>
                                saveGlobalValues(
                                  editorialShellFieldGroups.reduce((next, group) => {
                                    group.fields.forEach((field) => {
                                      next[field.key] = "";
                                    });
                                    return next;
                                  }, {})
                                ),
                              disabled: isSaving,
                            },
                            __("Restablecer valores globales", "pertenencia-digital")
                          )
                        )
                      : null
                  )
                )
              : null
          );
        },
      "withEditorialShellThemeInspector"
    );

    addFilter(
      "editor.BlockEdit",
      "pertenencia-digital/editorial-shell-theme-inspector",
      withEditorialShellThemeInspector
    );
  }

  const renderAccountInspector = (props) =>
    InspectorControls
      ? el(
          InspectorControls,
          {},
          el(
            PanelBody,
            {
              title: __("Contenido", "pertenencia-digital"),
              initialOpen: true,
            },
            renderTextControl(props, "loginLabel", __("Texto de acceso", "pertenencia-digital")),
            renderSelectControl(
              props,
              "triggerSize",
              __("Tamano del trigger", "pertenencia-digital"),
              [
                { label: __("Pequeno", "pertenencia-digital"), value: "small" },
                { label: __("Mediano", "pertenencia-digital"), value: "medium" },
                { label: __("Grande", "pertenencia-digital"), value: "large" },
              ]
            ),
            el(RangeControl, {
              label: __("Escala fina del trigger", "pertenencia-digital"),
              value: props.attributes.triggerScale || 100,
              min: 70,
              max: 150,
              step: 5,
              onChange: (value) => props.setAttributes({ triggerScale: value }),
            }),
            renderResetNumericButton(props, { triggerSize: "medium", triggerScale: 100, avatarSize: 40 }, __("Restablecer tamanos del bloque", "pertenencia-digital")),
            renderToggleControl(props, "showIdentity", __("Mostrar identidad en el panel", "pertenencia-digital")),
            renderToggleControl(props, "showEmail", __("Mostrar correo en el panel", "pertenencia-digital")),
            renderToggleControl(props, "showMembershipLink", __("Mostrar enlace a Mi pertenencia", "pertenencia-digital")),
            renderToggleControl(props, "showLogoutLink", __("Mostrar enlace de cerrar sesion", "pertenencia-digital")),
            el(RangeControl, {
              label: __("Tamano del avatar", "pertenencia-digital"),
              value: props.attributes.avatarSize || 40,
              min: 28,
              max: 96,
              step: 2,
              onChange: (value) => props.setAttributes({ avatarSize: value }),
            })
          ),
          el(
            PanelBody,
            {
              title: __("Panel", "pertenencia-digital"),
              initialOpen: false,
            },
            renderSelectControl(
              props,
              "panelAlign",
              __("Alineacion del panel", "pertenencia-digital"),
              [
                { label: __("Derecha", "pertenencia-digital"), value: "end" },
                { label: __("Izquierda", "pertenencia-digital"), value: "start" },
              ]
            ),
            renderTextControl(
              props,
              "panelWidth",
              __("Ancho del panel", "pertenencia-digital"),
              __("Acepta valores CSS como 18rem, 320px o min(22rem, 90vw).", "pertenencia-digital")
            ),
            el(RangeControl, {
              label: __("Tamano del texto del panel", "pertenencia-digital"),
              value: props.attributes.panelTextSize || 16,
              min: 12,
              max: 28,
              step: 1,
              onChange: (value) => props.setAttributes({ panelTextSize: value }),
            }),
            renderResetNumericButton(props, { panelTextSize: 16 }, __("Restablecer texto del panel", "pertenencia-digital"))
          ),
          el(
            PanelBody,
            {
              title: __("Colores de instancia", "pertenencia-digital"),
              initialOpen: false,
            },
            renderColorControl(props, "triggerBackground", __("Fondo del trigger", "pertenencia-digital")),
            renderColorControl(props, "triggerText", __("Texto del trigger", "pertenencia-digital")),
            renderColorControl(props, "triggerBorder", __("Borde del trigger", "pertenencia-digital")),
            renderColorControl(props, "panelBackground", __("Fondo del panel", "pertenencia-digital")),
            renderColorControl(props, "panelText", __("Texto del panel", "pertenencia-digital")),
            renderColorControl(props, "panelBorder", __("Borde del panel", "pertenencia-digital")),
            renderResetButton(
              props,
              [
                "triggerBackground",
                "triggerText",
                "triggerBorder",
                "panelBackground",
                "panelText",
                "panelBorder",
              ]
            )
          )
        )
      : null;

  const renderNavigationInspector = (props) =>
    InspectorControls
      ? el(
          InspectorControls,
          {},
          el(
            PanelBody,
            {
              title: __("Contenido", "pertenencia-digital"),
              initialOpen: true,
            },
            renderTextControl(props, "toggleLabel", __("Texto del boton", "pertenencia-digital")),
            renderSelectControl(
              props,
              "triggerSize",
              __("Tamano del trigger", "pertenencia-digital"),
              [
                { label: __("Pequeno", "pertenencia-digital"), value: "small" },
                { label: __("Mediano", "pertenencia-digital"), value: "medium" },
                { label: __("Grande", "pertenencia-digital"), value: "large" },
              ]
            ),
            el(RangeControl, {
              label: __("Escala fina del trigger", "pertenencia-digital"),
              value: props.attributes.triggerScale || 100,
              min: 70,
              max: 150,
              step: 5,
              onChange: (value) => props.setAttributes({ triggerScale: value }),
            }),
            renderResetNumericButton(props, { triggerSize: "medium", triggerScale: 100 }, __("Restablecer tamanos del bloque", "pertenencia-digital")),
            renderTextControl(props, "menuLocation", __("Ubicacion del menu clasico", "pertenencia-digital")),
            el(TextControl, {
              label: __("ID de navegacion FSE", "pertenencia-digital"),
              type: "number",
              value: props.attributes.ref || 0,
              onChange: (value) => props.setAttributes({ ref: Number(value) || 0 }),
            })
          ),
          el(
            PanelBody,
            {
              title: __("Comportamiento", "pertenencia-digital"),
              initialOpen: false,
            },
            renderSelectControl(
              props,
              "panelAlign",
              __("Alineacion del panel", "pertenencia-digital"),
              [
                { label: __("Izquierda", "pertenencia-digital"), value: "start" },
                { label: __("Derecha", "pertenencia-digital"), value: "end" },
              ]
            ),
            renderTextControl(
              props,
              "panelWidth",
              __("Ancho del panel", "pertenencia-digital"),
              __("Acepta valores CSS como 24rem, 360px o min(26rem, 92vw).", "pertenencia-digital")
            ),
            el(RangeControl, {
              label: __("Tamano del texto del menu", "pertenencia-digital"),
              value: props.attributes.menuTextSize || 16,
              min: 12,
              max: 28,
              step: 1,
              onChange: (value) => props.setAttributes({ menuTextSize: value }),
            }),
            renderResetNumericButton(props, { menuTextSize: 16 }, __("Restablecer texto del menu", "pertenencia-digital")),
            renderToggleControl(props, "hideLabelOnMobile", __("Ocultar texto del boton en movil", "pertenencia-digital")),
            renderToggleControl(props, "closeOnItemClick", __("Cerrar al hacer clic en un enlace", "pertenencia-digital")),
            el(RangeControl, {
              label: __("Retraso entre items (ms)", "pertenencia-digital"),
              value: props.attributes.staggerStep || 45,
              min: 0,
              max: 120,
              step: 5,
              onChange: (value) => props.setAttributes({ staggerStep: value }),
            })
          ),
          el(
            PanelBody,
            {
              title: __("Colores de instancia", "pertenencia-digital"),
              initialOpen: false,
            },
            renderColorControl(props, "triggerBackground", __("Fondo del trigger", "pertenencia-digital")),
            renderColorControl(props, "triggerText", __("Texto del trigger", "pertenencia-digital")),
            renderColorControl(props, "triggerBorder", __("Borde del trigger", "pertenencia-digital")),
            renderColorControl(props, "panelBackground", __("Fondo del panel", "pertenencia-digital")),
            renderColorControl(props, "panelText", __("Texto del panel", "pertenencia-digital")),
            renderColorControl(props, "panelBorder", __("Borde del panel", "pertenencia-digital")),
            renderColorControl(props, "itemBackground", __("Fondo de los enlaces", "pertenencia-digital")),
            renderColorControl(props, "itemBorder", __("Borde de los enlaces", "pertenencia-digital")),
            renderColorControl(props, "itemHoverBackground", __("Fondo hover/activo", "pertenencia-digital")),
            renderColorControl(props, "itemHoverBorder", __("Borde hover/activo", "pertenencia-digital")),
            renderColorControl(props, "itemHoverText", __("Texto hover/activo", "pertenencia-digital")),
            renderResetButton(
              props,
              [
                "triggerBackground",
                "triggerText",
                "triggerBorder",
                "panelBackground",
                "panelText",
                "panelBorder",
                "itemBackground",
                "itemBorder",
                "itemHoverBackground",
                "itemHoverBorder",
                "itemHoverText",
              ]
            )
          )
        )
      : null;

  const renderLoginPanelInspector = (props) =>
    InspectorControls
      ? el(
          InspectorControls,
          {},
          el(
            PanelBody,
            {
              title: __("Contenido", "pertenencia-digital"),
              initialOpen: true,
            },
            renderTextControl(props, "title", __("Titulo", "pertenencia-digital")),
            renderTextControl(props, "intro", __("Texto introductorio", "pertenencia-digital"))
          ),
          el(
            PanelBody,
            {
              title: __("Colores de textos y acentos", "pertenencia-digital"),
              initialOpen: false,
            },
            renderColorControl(props, "eyebrowColor", __("Eyebrow y meta", "pertenencia-digital")),
            renderColorControl(props, "titleColor", __("Titulos", "pertenencia-digital")),
            renderColorControl(props, "introTextColor", __("Texto del panel introductorio", "pertenencia-digital")),
            renderColorControl(props, "linkColor", __("Acento y enlaces", "pertenencia-digital")),
            renderColorControl(props, "linkHoverColor", __("Hover de enlaces", "pertenencia-digital"))
          ),
          el(
            PanelBody,
            {
              title: __("Secciones", "pertenencia-digital"),
              initialOpen: false,
            },
            renderColorControl(props, "introBackground", __("Fondo del panel introductorio", "pertenencia-digital")),
            renderColorControl(props, "introGlow", __("Glow del panel introductorio", "pertenencia-digital")),
            renderColorControl(props, "featureBackground", __("Fondo de tarjetas intro", "pertenencia-digital")),
            renderColorControl(props, "featureText", __("Texto de tarjetas intro", "pertenencia-digital")),
            renderColorControl(props, "cardBackground", __("Fondo del card principal", "pertenencia-digital")),
            renderColorControl(props, "cardText", __("Texto del card principal", "pertenencia-digital")),
            renderColorControl(props, "cardBorder", __("Borde del card principal", "pertenencia-digital")),
            renderColorControl(props, "supportBackground", __("Fondo de tarjetas de apoyo", "pertenencia-digital"))
          ),
          el(
            PanelBody,
            {
              title: __("Campos y botones", "pertenencia-digital"),
              initialOpen: false,
            },
            renderColorControl(props, "fieldBackground", __("Fondo de campos", "pertenencia-digital")),
            renderColorControl(props, "fieldText", __("Texto de campos", "pertenencia-digital")),
            renderColorControl(props, "fieldBorder", __("Borde de campos", "pertenencia-digital")),
            renderColorControl(props, "buttonBackground", __("Fondo de botones", "pertenencia-digital")),
            renderColorControl(props, "buttonText", __("Texto de botones", "pertenencia-digital")),
            renderColorControl(props, "buttonBorder", __("Borde de botones", "pertenencia-digital")),
            renderResetButton(
              props,
              [
                "eyebrowColor",
                "titleColor",
                "introTextColor",
                "introBackground",
                "introGlow",
                "featureBackground",
                "featureText",
                "cardBackground",
                "cardText",
                "cardBorder",
                "fieldBackground",
                "fieldText",
                "fieldBorder",
                "linkColor",
                "linkHoverColor",
                "buttonBackground",
                "buttonText",
                "buttonBorder",
                "supportBackground",
              ]
            )
          )
        )
      : null;

  const renderMusicAccessGateInspector = (props) =>
    InspectorControls
      ? el(
          InspectorControls,
          {},
          el(
            PanelBody,
            {
              title: __("Contenido", "pertenencia-digital"),
              initialOpen: true,
            },
            renderSelectControl(
              props,
              "context",
              __("Contexto privado", "pertenencia-digital"),
              [
                { label: __("Estudiar repertorio", "pertenencia-digital"), value: "study-repertoire" },
                { label: __("Mi pertenencia", "pertenencia-digital"), value: "membership" },
                { label: __("Ensayos", "pertenencia-digital"), value: "rehearsals" },
              ]
            ),
            renderToggleControl(
              props,
              "useMainAccessColors",
              __("Colores del principal", "pertenencia-digital"),
              __("Trae los colores del bloque principal de acceso en la plantilla Acceso.", "pertenencia-digital")
            ),
            renderTextControl(
              props,
              "intro",
              __("Texto introductorio opcional", "pertenencia-digital"),
              __("Si lo dejas vacio, el bloque usa el copy por defecto del contexto.", "pertenencia-digital")
            )
          ),
          !props.attributes.useMainAccessColors
            ? el(
                PanelBody,
                {
                  title: __("Marco exterior", "pertenencia-digital"),
                  initialOpen: false,
                },
                renderColorControl(props, "shellBackground", __("Fondo exterior", "pertenencia-digital")),
                renderColorControl(props, "shellBorder", __("Borde exterior", "pertenencia-digital"))
              )
            : null,
          !props.attributes.useMainAccessColors
            ? el(
                PanelBody,
                {
                  title: __("Colores de textos y acentos", "pertenencia-digital"),
                  initialOpen: false,
                },
                renderColorControl(props, "eyebrowColor", __("Eyebrow y meta", "pertenencia-digital")),
                renderColorControl(props, "titleColor", __("Titulos", "pertenencia-digital")),
                renderColorControl(props, "introTextColor", __("Texto del panel introductorio", "pertenencia-digital")),
                renderColorControl(props, "linkColor", __("Acento y enlaces", "pertenencia-digital")),
                renderColorControl(props, "linkHoverColor", __("Hover de enlaces", "pertenencia-digital"))
              )
            : null,
          !props.attributes.useMainAccessColors
            ? el(
                PanelBody,
                {
                  title: __("Secciones", "pertenencia-digital"),
                  initialOpen: false,
                },
                renderColorControl(props, "introBackground", __("Fondo del panel introductorio", "pertenencia-digital")),
                renderColorControl(props, "introGlow", __("Glow del panel introductorio", "pertenencia-digital")),
                renderColorControl(props, "featureBackground", __("Fondo de tarjetas intro", "pertenencia-digital")),
                renderColorControl(props, "featureText", __("Texto de tarjetas intro", "pertenencia-digital")),
                renderColorControl(props, "cardBackground", __("Fondo del card principal", "pertenencia-digital")),
                renderColorControl(props, "cardText", __("Texto del card principal", "pertenencia-digital")),
                renderColorControl(props, "cardBorder", __("Borde del card principal", "pertenencia-digital")),
                renderColorControl(props, "supportBackground", __("Fondo de tarjetas de apoyo", "pertenencia-digital"))
              )
            : null,
          !props.attributes.useMainAccessColors
            ? el(
                PanelBody,
                {
                  title: __("Campos y botones", "pertenencia-digital"),
                  initialOpen: false,
                },
                renderColorControl(props, "fieldBackground", __("Fondo de campos", "pertenencia-digital")),
                renderColorControl(props, "fieldText", __("Texto de campos", "pertenencia-digital")),
                renderColorControl(props, "fieldBorder", __("Borde de campos", "pertenencia-digital")),
                renderColorControl(props, "buttonBackground", __("Fondo de botones", "pertenencia-digital")),
                renderColorControl(props, "buttonText", __("Texto de botones", "pertenencia-digital")),
                renderColorControl(props, "buttonBorder", __("Borde de botones", "pertenencia-digital")),
                renderResetButton(
                  props,
                  [
                    "shellBackground",
                    "shellBorder",
                    "eyebrowColor",
                    "titleColor",
                    "introTextColor",
                    "introBackground",
                    "introGlow",
                    "featureBackground",
                    "featureText",
                    "cardBackground",
                    "cardText",
                    "cardBorder",
                    "fieldBackground",
                    "fieldText",
                    "fieldBorder",
                    "linkColor",
                    "linkHoverColor",
                    "buttonBackground",
                    "buttonText",
                    "buttonBorder",
                    "supportBackground",
                  ],
                  __("Restablecer colores locales", "pertenencia-digital")
                )
              )
            : null
        )
      : null;

  const renderMusicSubnavigationInspector = (props) =>
    InspectorControls
      ? el(
          InspectorControls,
          {},
          el(
            PanelBody,
            {
              title: __("Contenido", "pertenencia-digital"),
              initialOpen: true,
            },
            renderTextControl(props, "parentPath", __("Slug padre", "pertenencia-digital")),
            renderSelectControl(
              props,
              "alignItems",
              __("Alineacion", "pertenencia-digital"),
              [
                { label: __("Izquierda", "pertenencia-digital"), value: "start" },
                { label: __("Centro", "pertenencia-digital"), value: "center" },
                { label: __("Derecha", "pertenencia-digital"), value: "end" },
              ]
            ),
            renderSelectControl(
              props,
              "mobileMode",
              __("Modo movil", "pertenencia-digital"),
              [
                { label: __("Scroll horizontal", "pertenencia-digital"), value: "scroll" },
                { label: __("Wrap", "pertenencia-digital"), value: "wrap" },
              ]
            )
          ),
          el(
            PanelBody,
            {
              title: __("Tamano", "pertenencia-digital"),
              initialOpen: false,
            },
            renderSelectControl(
              props,
              "itemSize",
              __("Tamano base de pestañas", "pertenencia-digital"),
              [
                { label: __("Pequeno", "pertenencia-digital"), value: "small" },
                { label: __("Mediano", "pertenencia-digital"), value: "medium" },
                { label: __("Grande", "pertenencia-digital"), value: "large" },
              ]
            ),
            el(RangeControl, {
              label: __("Escala fina", "pertenencia-digital"),
              value: props.attributes.itemScale || 100,
              min: 70,
              max: 150,
              step: 5,
              onChange: (value) => props.setAttributes({ itemScale: value }),
            }),
            renderResetNumericButton(props, { itemSize: "medium", itemScale: 100 }, __("Restablecer tamanos del bloque", "pertenencia-digital"))
          ),
          el(
            PanelBody,
            {
              title: __("Colores de instancia", "pertenencia-digital"),
              initialOpen: false,
            },
            renderColorControl(props, "textColor", __("Texto", "pertenencia-digital")),
            renderColorControl(props, "itemHoverBackground", __("Fondo hover", "pertenencia-digital")),
            renderColorControl(props, "itemHoverBorder", __("Borde hover", "pertenencia-digital")),
            renderColorControl(props, "itemCurrentBackground", __("Fondo actual", "pertenencia-digital")),
            renderColorControl(props, "itemCurrentBorder", __("Borde actual", "pertenencia-digital")),
            renderColorControl(props, "itemCurrentText", __("Texto actual", "pertenencia-digital")),
            renderResetButton(
              props,
              [
                "textColor",
                "itemHoverBackground",
                "itemHoverBorder",
                "itemCurrentBackground",
                "itemCurrentBorder",
                "itemCurrentText",
              ]
            )
          )
        )
      : null;

  const registerThemeBlock = (name, settings) => {
    if (!getBlockType(name)) {
      registerBlockType(name, settings);
    }
  };

  registerThemeBlock("pertenencia-digital/account-access", {
    apiVersion: 3,
    title: __("Acceso de usuario", "pertenencia-digital"),
    icon: "admin-users",
    category: "widgets",
    attributes: {
      loginLabel: { type: "string", default: "Acceso" },
      showIdentity: { type: "boolean", default: true },
      showEmail: { type: "boolean", default: true },
      showMembershipLink: { type: "boolean", default: true },
      showLogoutLink: { type: "boolean", default: true },
      panelAlign: { type: "string", default: "end" },
      triggerSize: { type: "string", default: "medium" },
      triggerScale: { type: "number", default: 100 },
      panelWidth: { type: "string", default: "18rem" },
      panelTextSize: { type: "number", default: 16 },
      avatarSize: { type: "number", default: 40 },
      triggerBackground: { type: "string", default: "" },
      triggerText: { type: "string", default: "" },
      triggerBorder: { type: "string", default: "" },
      panelBackground: { type: "string", default: "" },
      panelText: { type: "string", default: "" },
      panelBorder: { type: "string", default: "" },
    },
    supports: {
      html: false,
    },
    edit: (props) =>
      el(
        Fragment,
        {},
        renderAccountInspector(props),
        renderServerPreview("pertenencia-digital/account-access", props)
      ),
    save: () => null,
  });

  registerThemeBlock("pertenencia-digital/login-panel", {
    apiVersion: 3,
    title: __("Panel de acceso", "pertenencia-digital"),
    icon: "lock",
    category: "widgets",
    attributes: {
      title: { type: "string", default: "Accede a tu pertenencia digital" },
      intro: {
        type: "string",
        default:
          "Usa esta pantalla para iniciar sesión, recuperar tu contraseña y volver a tu espacio con una interfaz frontal más clara y estable.",
      },
      eyebrowColor: { type: "string", default: "" },
      titleColor: { type: "string", default: "" },
      introTextColor: { type: "string", default: "" },
      introBackground: { type: "string", default: "" },
      introGlow: { type: "string", default: "" },
      featureBackground: { type: "string", default: "" },
      featureText: { type: "string", default: "" },
      cardBackground: { type: "string", default: "" },
      cardText: { type: "string", default: "" },
      cardBorder: { type: "string", default: "" },
      fieldBackground: { type: "string", default: "" },
      fieldText: { type: "string", default: "" },
      fieldBorder: { type: "string", default: "" },
      linkColor: { type: "string", default: "" },
      linkHoverColor: { type: "string", default: "" },
      buttonBackground: { type: "string", default: "" },
      buttonText: { type: "string", default: "" },
      buttonBorder: { type: "string", default: "" },
      supportBackground: { type: "string", default: "" },
    },
    supports: {
      color: {
        background: true,
        gradients: true,
        text: true,
      },
      border: {
        color: true,
        radius: true,
      },
      spacing: {
        margin: true,
        padding: true,
      },
      typography: {
        fontSize: true,
        lineHeight: true,
      },
      html: false,
    },
    edit: (props) =>
      el(
        Fragment,
        {},
        renderLoginPanelInspector(props),
        renderServerPreview("pertenencia-digital/login-panel", props)
      ),
    save: () => null,
  });

  registerThemeBlock("pertenencia-digital/site-navigation", {
    apiVersion: 3,
    title: __("Navegacion del tema", "pertenencia-digital"),
    icon: "menu",
    category: "widgets",
    attributes: {
      ref: { type: "number", default: 0 },
      menuLocation: { type: "string", default: "menu_principal" },
      toggleLabel: { type: "string", default: "Menu" },
      panelAlign: { type: "string", default: "start" },
      triggerSize: { type: "string", default: "medium" },
      triggerScale: { type: "number", default: 100 },
      panelWidth: { type: "string", default: "24rem" },
      menuTextSize: { type: "number", default: 16 },
      hideLabelOnMobile: { type: "boolean", default: false },
      closeOnItemClick: { type: "boolean", default: true },
      staggerStep: { type: "number", default: 45 },
      triggerBackground: { type: "string", default: "" },
      triggerText: { type: "string", default: "" },
      triggerBorder: { type: "string", default: "" },
      panelBackground: { type: "string", default: "" },
      panelText: { type: "string", default: "" },
      panelBorder: { type: "string", default: "" },
      itemBackground: { type: "string", default: "" },
      itemBorder: { type: "string", default: "" },
      itemHoverBackground: { type: "string", default: "" },
      itemHoverBorder: { type: "string", default: "" },
      itemHoverText: { type: "string", default: "" },
    },
    supports: {
      html: false,
    },
    edit: (props) =>
      el(
        Fragment,
        {},
        renderNavigationInspector(props),
        renderServerPreview("pertenencia-digital/site-navigation", props)
      ),
    save: () => null,
  });

  registerThemeBlock("pertenencia-digital/music-subnavigation", {
    apiVersion: 3,
    title: __("Subnavegacion de seccion", "pertenencia-digital"),
    icon: "playlist-audio",
    category: "widgets",
    attributes: {
      parentPath: { type: "string", default: "musica" },
      alignItems: { type: "string", default: "center" },
      mobileMode: { type: "string", default: "scroll" },
      itemSize: { type: "string", default: "medium" },
      itemScale: { type: "number", default: 100 },
      textColor: { type: "string", default: "" },
      itemHoverBackground: { type: "string", default: "" },
      itemHoverBorder: { type: "string", default: "" },
      itemCurrentBackground: { type: "string", default: "" },
      itemCurrentBorder: { type: "string", default: "" },
      itemCurrentText: { type: "string", default: "" },
    },
    supports: {
      html: false,
    },
    edit: (props) =>
      el(
        Fragment,
        {},
        renderMusicSubnavigationInspector(props),
        renderServerPreview("pertenencia-digital/music-subnavigation", props)
      ),
    save: () => null,
  });

  registerThemeBlock("pertenencia-digital/music-access-gate", {
    apiVersion: 3,
    title: __("Gate privado de musica", "pertenencia-digital"),
    icon: "shield",
    category: "widgets",
    attributes: {
      context: { type: "string", default: "study-repertoire" },
      useMainAccessColors: { type: "boolean", default: true },
      intro: { type: "string", default: "" },
      shellBackground: { type: "string", default: "" },
      shellBorder: { type: "string", default: "" },
      eyebrowColor: { type: "string", default: "" },
      titleColor: { type: "string", default: "" },
      introTextColor: { type: "string", default: "" },
      introBackground: { type: "string", default: "" },
      introGlow: { type: "string", default: "" },
      featureBackground: { type: "string", default: "" },
      featureText: { type: "string", default: "" },
      cardBackground: { type: "string", default: "" },
      cardText: { type: "string", default: "" },
      cardBorder: { type: "string", default: "" },
      fieldBackground: { type: "string", default: "" },
      fieldText: { type: "string", default: "" },
      fieldBorder: { type: "string", default: "" },
      linkColor: { type: "string", default: "" },
      linkHoverColor: { type: "string", default: "" },
      buttonBackground: { type: "string", default: "" },
      buttonText: { type: "string", default: "" },
      buttonBorder: { type: "string", default: "" },
      supportBackground: { type: "string", default: "" },
    },
    supports: {
      color: {
        background: true,
        gradients: true,
        text: true,
      },
      border: {
        color: true,
        radius: true,
      },
      spacing: {
        margin: true,
        padding: true,
      },
      typography: {
        fontSize: true,
        lineHeight: true,
      },
      html: false,
    },
    edit: (props) =>
      el(
        Fragment,
        {},
        renderMusicAccessGateInspector(props),
        renderServerPreview("pertenencia-digital/music-access-gate", props)
      ),
    save: () => null,
  });
})(window.wp);
