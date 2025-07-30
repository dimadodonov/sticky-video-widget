document.addEventListener("DOMContentLoaded", () => {
  const widget = document.querySelector(".video-widget");
  if (!widget) return;

  const video = document.getElementById("video-widget__video");
  const close = widget.querySelector(".video-widget__close");
  const container = widget.querySelector(".video-widget__container");
  const button = widget.querySelector(".video-widget__button");

  // Проверяем, есть ли автозапуск
  const hasAutoplay = video.hasAttribute("autoplay");

  function open() {
    widget.setAttribute("data-state", "opened");
    video.currentTime = 0;
    video.muted = false;

    // Если видео не имеет автозапуска, запускаем его при открытии
    if (!hasAutoplay) {
      video.play().catch((e) => console.log("Autoplay prevented:", e));
    }
  }

  function closeWidget() {
    widget.setAttribute("data-state", "default");
    video.muted = true;

    // Если видео не имеет автозапуска, ставим на паузу
    if (!hasAutoplay) {
      video.pause();
    }
  }

  function toggleWidget() {
    const state = widget.getAttribute("data-state");
    state === "default" ? open() : closeWidget();
  }

  // Обработчики событий
  close.addEventListener("click", (e) => {
    e.preventDefault();
    e.stopPropagation();
    closeWidget();
  });

  container.addEventListener("click", (e) => {
    // Не срабатывает, если кликнули по кнопке
    if (e.target === button || button.contains(e.target)) {
      return;
    }
    toggleWidget();
  });

  // Для тачскринов
  if (window.innerWidth > 1024) {
    container.addEventListener("touchstart", (e) => {
      if (e.target === button || button.contains(e.target)) {
        return;
      }
      toggleWidget();
    });
  }

  // Закрытие при клике вне виджета
  document.addEventListener("mouseup", (e) => {
    if (
      !widget.contains(e.target) &&
      widget.getAttribute("data-state") !== "default"
    ) {
      closeWidget();
    }
  });

  // Обработка ошибок видео
  video.addEventListener("error", (e) => {
    console.error("Video error:", e);
    widget.style.display = "none";
  });

  // Плавное появление виджета
  setTimeout(() => {
    widget.style.opacity = "1";
  }, 500);
});
