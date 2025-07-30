document.addEventListener("DOMContentLoaded", () => {
  const widget = document.querySelector(".video-widget");
  if (!widget) return;

  const video = document.getElementById("video-widget__video");
  const close = widget.querySelector(".video-widget__close");
  const container = widget.querySelector(".video-widget__container");
  const button = widget.querySelector(".video-widget__button");

  // Проверяем, есть ли автозапуск
  const hasAutoplay = video.hasAttribute("autoplay");

  // Ключ для localStorage
  const STORAGE_KEY = "svw_widget_closed";
  const STORAGE_DURATION = 24 * 60 * 60 * 1000; // 24 часа в миллисекундах

  // Проверяем, был ли виджет закрыт ранее
  function isWidgetClosed() {
    const closedData = localStorage.getItem(STORAGE_KEY);
    if (!closedData) return false;

    try {
      const { timestamp } = JSON.parse(closedData);
      const now = Date.now();

      // Если прошло больше 24 часов, показываем виджет снова
      if (now - timestamp > STORAGE_DURATION) {
        localStorage.removeItem(STORAGE_KEY);
        return false;
      }

      return true;
    } catch (e) {
      localStorage.removeItem(STORAGE_KEY);
      return false;
    }
  }

  // Сохраняем состояние закрытия
  function saveClosedState() {
    const data = {
      timestamp: Date.now(),
    };
    localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
  }

  // Скрываем виджет полностью
  function hideWidget() {
    widget.style.display = "none";
    saveClosedState();
  }

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
    hideWidget(); // Полностью скрываем виджет и сохраняем в localStorage
  });

  container.addEventListener("click", (e) => {
    // Не срабатывает, если кликнули по кнопке или кнопке закрытия
    if (
      e.target === button ||
      button.contains(e.target) ||
      e.target === close ||
      close.contains(e.target)
    ) {
      return;
    }
    toggleWidget();
  });

  // Для тачскринов
  if (window.innerWidth > 1024) {
    container.addEventListener("touchstart", (e) => {
      if (
        e.target === button ||
        button.contains(e.target) ||
        e.target === close ||
        close.contains(e.target)
      ) {
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

  // Инициализация виджета
  function initWidget() {
    // Проверяем, был ли виджет закрыт ранее
    if (isWidgetClosed()) {
      widget.style.display = "none";
      return;
    }

    // Плавное появление виджета
    setTimeout(() => {
      widget.style.opacity = "1";
    }, 500);
  }

  // Запускаем инициализацию
  initWidget();
});
