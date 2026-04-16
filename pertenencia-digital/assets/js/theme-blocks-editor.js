(function (wp) {
  if (!wp || !wp.blocks || !wp.element) {
    return;
  }

  const { registerBlockType, getBlockType } = wp.blocks;
  const { createElement: el, Fragment } = wp.element;
  const { __ } = wp.i18n || { __: (value) => value };
  const ServerSideRender = wp.serverSideRender;
  const { InspectorControls, useBlockProps } = wp.blockEditor || {};
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
  const palette =
    wp.data && typeof wp.data.select === "function"
      ? (wp.data.select("core/block-editor")?.getSettings?.().colors || [])
      : [];

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
    title: __("Subnavegacion de Musica", "pertenencia-digital"),
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
})(window.wp);
