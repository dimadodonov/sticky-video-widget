jQuery(document).ready(function ($) {

  // ====================================================
  // Settings Page — Video media picker
  // ====================================================
  if ($("#svw_select_button").length) {
    let file_frame;

    function triggerPreviewUpdate() {
      if (typeof window.updateSVWPreview === "function") {
        window.updateSVWPreview();
      }
      $("#svw_video_url").trigger("input");
    }

    $("#svw_select_button").on("click", function (e) {
      e.preventDefault();
      if (file_frame) {
        file_frame.open();
        return;
      }
      file_frame = wp.media({
        title: "Выберите видео",
        button: { text: "Использовать это видео" },
        library: { type: "video" },
        multiple: false,
      });
      file_frame.on("select", function () {
        const attachment = file_frame.state().get("selection").first().toJSON();
        $("#svw_video_url").val(attachment.url);
        triggerPreviewUpdate();
      });
      file_frame.open();
    });

    $("#svw_clear_button").on("click", function () {
      $("#svw_video_url").val("");
      triggerPreviewUpdate();
    });
  }

  // ====================================================
  // Settings Page — Poster media picker
  // ====================================================
  if ($("#svw_select_poster_button").length) {
    let poster_frame;

    $("#svw_select_poster_button").on("click", function (e) {
      e.preventDefault();
      if (poster_frame) {
        poster_frame.open();
        return;
      }
      poster_frame = wp.media({
        title: "Выберите постер",
        button: { text: "Использовать это изображение" },
        library: { type: "image" },
        multiple: false,
      });
      poster_frame.on("select", function () {
        const attachment = poster_frame.state().get("selection").first().toJSON();
        $("#svw_video_poster").val(attachment.url);
        $("#svw_poster_preview").attr("src", attachment.url).show();
      });
      poster_frame.open();
    });

    $("#svw_clear_poster_button").on("click", function () {
      $("#svw_video_poster").val("");
      $("#svw_poster_preview").attr("src", "").hide();
    });
  }

  // ====================================================
  // Settings Page — Select2 для выбора страниц (правила отображения)
  // ====================================================
  if ($("#svw_display_pages").length && typeof $.fn.select2 !== "undefined") {
    $("#svw_display_pages").select2({
      placeholder: "Поиск по названию страницы или записи...",
      allowClear: true,
      minimumInputLength: 1,
      ajax: {
        url: typeof svwAdmin !== "undefined" ? svwAdmin.ajax_url : "",
        dataType: "json",
        delay: 300,
        data: function (params) {
          return {
            action: "svw_search_posts",
            q: params.term,
            nonce: typeof svwAdmin !== "undefined" ? svwAdmin.nonce : "",
          };
        },
        processResults: function (data) {
          return data;
        },
        cache: true,
      },
    });

    // Показывать/скрывать поля выбора страниц и типов при смене режима
    function toggleDisplayRulesFields() {
      const mode = $("input[name='svw_display_mode']:checked").val();
      const pagesRow = $("#svw_display_pages").closest("tr");
      const typesRow = $("input[name='svw_display_post_types[]']").first().closest("tr");

      if (mode === "all") {
        pagesRow.hide();
        typesRow.hide();
      } else {
        pagesRow.show();
        typesRow.show();
      }
    }

    $("input[name='svw_display_mode']").on("change", toggleDisplayRulesFields);
    // Инициализация при загрузке
    toggleDisplayRulesFields();
  }

  // ====================================================
  // Meta Box — Video and Poster media pickers (post/page editor)
  // ====================================================
  if ($("#svw_meta_video_url").length) {
    let meta_video_frame;
    let meta_poster_frame;

    $(".svw-meta-select-video").on("click", function (e) {
      e.preventDefault();
      if (meta_video_frame) {
        meta_video_frame.open();
        return;
      }
      meta_video_frame = wp.media({
        title: "Выберите видео для страницы",
        button: { text: "Использовать это видео" },
        library: { type: "video" },
        multiple: false,
      });
      meta_video_frame.on("select", function () {
        const attachment = meta_video_frame.state().get("selection").first().toJSON();
        $("#svw_meta_video_url").val(attachment.url);
      });
      meta_video_frame.open();
    });

    $(".svw-meta-clear-video").on("click", function () {
      $("#svw_meta_video_url").val("");
    });

    $(".svw-meta-select-poster").on("click", function (e) {
      e.preventDefault();
      if (meta_poster_frame) {
        meta_poster_frame.open();
        return;
      }
      meta_poster_frame = wp.media({
        title: "Выберите постер для страницы",
        button: { text: "Использовать это изображение" },
        library: { type: "image" },
        multiple: false,
      });
      meta_poster_frame.on("select", function () {
        const attachment = meta_poster_frame.state().get("selection").first().toJSON();
        $("#svw_meta_video_poster").val(attachment.url);
        $("#svw_meta_poster_preview").attr("src", attachment.url).show();
      });
      meta_poster_frame.open();
    });

    $(".svw-meta-clear-poster").on("click", function () {
      $("#svw_meta_video_poster").val("");
      $("#svw_meta_poster_preview").attr("src", "").hide();
    });
  }
});
