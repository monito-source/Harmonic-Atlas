(function (wp) {
  if (!wp || !wp.blocks || !wp.element) {
    return;
  }

  const { registerBlockType, getBlockType } = wp.blocks;
  const { createElement: el } = wp.element;
  const { __ } = wp.i18n || { __: (value) => value };
  const ServerSideRender = wp.serverSideRender;

  const renderServerPreview = (blockName, props) => {
    if (ServerSideRender) {
      return el(ServerSideRender, {
        block: blockName,
        attributes: props.attributes,
      });
    }

    return el(
      "p",
      {},
      __("La vista previa del bloque no esta disponible en este editor.", "pertenencia-digital")
    );
  };

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
    supports: {
      html: false,
    },
    edit: (props) => renderServerPreview("pertenencia-digital/account-access", props),
    save: () => null,
  });

  registerThemeBlock("pertenencia-digital/site-navigation", {
    apiVersion: 3,
    title: __("Navegacion del tema", "pertenencia-digital"),
    icon: "menu",
    category: "widgets",
    attributes: {
      ref: {
        type: "number",
        default: 0,
      },
      menuLocation: {
        type: "string",
        default: "menu_principal",
      },
      toggleLabel: {
        type: "string",
        default: "Menu",
      },
    },
    supports: {
      html: false,
    },
    edit: (props) => renderServerPreview("pertenencia-digital/site-navigation", props),
    save: () => null,
  });

  registerThemeBlock("pertenencia-digital/music-subnavigation", {
    apiVersion: 3,
    title: __("Subnavegacion de Musica", "pertenencia-digital"),
    icon: "playlist-audio",
    category: "widgets",
    attributes: {
      parentPath: {
        type: "string",
        default: "musica",
      },
    },
    supports: {
      html: false,
    },
    edit: (props) => renderServerPreview("pertenencia-digital/music-subnavigation", props),
    save: () => null,
  });
})(window.wp);
