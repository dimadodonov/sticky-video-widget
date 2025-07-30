jQuery(document).ready(function ($) {
  let file_frame;

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
    });

    file_frame.open();
  });

  $("#svw_clear_button").on("click", function () {
    $("#svw_video_url").val("");
  });
});
