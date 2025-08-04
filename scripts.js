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

  // Функция для отправки событий в Яндекс.Метрику
  function sendYandexMetrikaEvent(eventId, callback = null) {
    if (!eventId || eventId.trim() === "") {
      if (callback) callback();
      return;
    }

    // Проверяем наличие Яндекс.Метрики
    if (typeof ym !== "undefined") {
      // Получаем ID счетчика из настроек
      const counterId =
        typeof svwSettings !== "undefined" &&
        svwSettings.yandex_metrika_counter_id
          ? svwSettings.yandex_metrika_counter_id
          : null;

      if (counterId && counterId.trim() !== "") {
        console.log(
          "Sending Yandex Metrika event:",
          eventId,
          "Counter ID:",
          counterId
        );
        ym(counterId, "reachGoal", eventId, {
          callback: function () {
            console.log("Yandex Metrika callback executed for:", eventId);
            if (callback) callback();
          },
        });
        // Fallback на случай если callback не сработает
        setTimeout(() => {
          if (callback) callback();
        }, 1000);
      } else {
        console.log("Yandex Metrika Counter ID not configured");
        if (callback) callback();
      }
    } else {
      console.log("Yandex Metrika not found");
      if (callback) callback();
    }
  }

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
    // Останавливаем видео и сбрасываем время
    video.pause();
    video.currentTime = 0;
    video.muted = true;

    widget.style.display = "none";
    saveClosedState();
  }

  function open() {
    widget.setAttribute("data-state", "opened");
    video.currentTime = 0;
    video.muted = false;

    // Отправляем событие в Яндекс.Метрику при открытии виджета
    if (
      typeof svwSettings !== "undefined" &&
      svwSettings.yandex_metrika_widget_open
    ) {
      sendYandexMetrikaEvent(
        svwSettings.yandex_metrika_widget_open,
        function () {
          console.log("Widget opened event sent successfully");
        }
      );
    }

    // Если видео не имеет автозапуска, запускаем его при открытии
    if (!hasAutoplay) {
      video.play().catch((e) => console.log("Autoplay prevented:", e));
    }
  }

  function closeWidget() {
    widget.setAttribute("data-state", "default");

    // Останавливаем видео и сбрасываем время
    // video.pause();
    // video.currentTime = 0;
    video.muted = true;
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

  // Обработчик клика по кнопке для отправки события в Яндекс.Метрику
  button.addEventListener("click", (e) => {
    // Предотвращаем стандартное поведение ссылки
    e.preventDefault();

    const buttonUrl = button.getAttribute("href");

    // Отправляем событие в Яндекс.Метрику при клике на кнопку
    if (
      typeof svwSettings !== "undefined" &&
      svwSettings.yandex_metrika_button_click
    ) {
      sendYandexMetrikaEvent(
        svwSettings.yandex_metrika_button_click,
        function () {
          console.log("Button click event sent successfully");
          // После успешной отправки события перенаправляем пользователя
          if (buttonUrl) {
            if (buttonUrl.startsWith("#")) {
              // Если это якорь, плавно скроллим к элементу
              const targetElement = document.querySelector(buttonUrl);
              if (targetElement) {
                targetElement.scrollIntoView({ behavior: "smooth" });
              }
            } else {
              // Если это URL, переходим по ссылке
              window.location.href = buttonUrl;
            }
          }
        }
      );
    } else {
      // Если событие не настроено, сразу переходим по ссылке
      if (buttonUrl) {
        if (buttonUrl.startsWith("#")) {
          const targetElement = document.querySelector(buttonUrl);
          if (targetElement) {
            targetElement.scrollIntoView({ behavior: "smooth" });
          }
        } else {
          window.location.href = buttonUrl;
        }
      }
    }
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

    // Показываем виджет и делаем его видимым
    widget.style.display = "block";
    widget.style.visibility = "visible";

    // Плавное появление виджета
    setTimeout(() => {
      widget.style.opacity = "1";
    }, 500);
  }

  // Запускаем инициализацию
  initWidget();
});
