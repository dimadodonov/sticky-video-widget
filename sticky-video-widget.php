<?php
/*
Plugin Name: Sticky Video Widget
Description: Добавляет на сайт настраиваемый плавающий видео-виджет с возможностью выбора видео из медиатеки, настройкой позиции, текста кнопки и интеграцией с Яндекс.Метрикой.
Version: 1.2.0
Author: Mitroliti
Author URI: https://mitroliti.com
Plugin URI: https://mitroliti.com/plugins/sticky-video-widget
Text Domain: sticky-video-widget
Domain Path: /languages
*/

if (!defined('ABSPATH')) {
    exit;
}

// Инициализация плагина
function svw_init() {
    load_plugin_textdomain('sticky-video-widget', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'svw_init');

// Добавляем ссылку "Настройки" на странице плагинов
function svw_add_settings_link($links) {
    $settings_link = '<a href="' . admin_url('options-general.php?page=sticky-video-widget') . '">' . __('Настройки', 'sticky-video-widget') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'svw_add_settings_link');

// Уведомление после активации плагина
function svw_activation_notice() {
    if (get_transient('svw_activation_notice')) {
        ?>
        <div class="notice notice-success is-dismissible">
            <p>
                <?php _e('Спасибо за установку Sticky Video Widget!', 'sticky-video-widget'); ?> 
                <a href="<?php echo admin_url('options-general.php?page=sticky-video-widget'); ?>">
                    <?php _e('Настройте плагин сейчас', 'sticky-video-widget'); ?>
                </a>
            </p>
        </div>
        <?php
        delete_transient('svw_activation_notice');
    }
}
add_action('admin_notices', 'svw_activation_notice');

// Устанавливаем транзиент при активации
function svw_activate_plugin() {
    set_transient('svw_activation_notice', true, 30);
}
register_activation_hook(__FILE__, 'svw_activate_plugin');

// Подключение скриптов/стилей
function svw_enqueue_scripts($hook) {
    wp_enqueue_style('svw_styles', plugin_dir_url(__FILE__) . 'styles.css', array(), '1.2.0');
    
    if (!is_admin()) {
        wp_enqueue_script('svw_scripts', plugin_dir_url(__FILE__) . 'scripts.js', array(), '1.2.0', true);
        
        // Передаем настройки в JavaScript
        $settings = array(
            'autoplay' => get_option('svw_autoplay', '1'),
            'yandex_metrika_counter_id' => get_option('svw_yandex_metrika_counter_id', ''),
            'yandex_metrika_widget_open' => get_option('svw_yandex_metrika_widget_open', ''),
            'yandex_metrika_button_click' => get_option('svw_yandex_metrika_button_click', '')
        );
        wp_localize_script('svw_scripts', 'svwSettings', $settings);
    }

    if ($hook === 'settings_page_sticky-video-widget') {
        wp_enqueue_media();
        wp_enqueue_script('svw_admin_scripts', plugin_dir_url(__FILE__) . 'admin-scripts.js', array('jquery'), '1.2.0', true);
    }
}
add_action('admin_enqueue_scripts', 'svw_enqueue_scripts');
add_action('wp_enqueue_scripts', 'svw_enqueue_scripts');

// Меню в админке
function svw_add_settings_page() {
    add_options_page('Sticky Video Widget', 'Sticky Video Widget', 'manage_options', 'sticky-video-widget', 'svw_render_settings_page');
}
add_action('admin_menu', 'svw_add_settings_page');

// Регистрация опций
function svw_register_settings() {
    register_setting('svw_settings_group', 'svw_video_url');
    register_setting('svw_settings_group', 'svw_button_text');
    register_setting('svw_settings_group', 'svw_button_link');

    register_setting('svw_settings_group', 'svw_widget_enabled');
    register_setting('svw_settings_group', 'svw_show_on_mobile');
    register_setting('svw_settings_group', 'svw_autoplay');
    register_setting('svw_settings_group', 'svw_yandex_metrika_counter_id');
    register_setting('svw_settings_group', 'svw_yandex_metrika_widget_open');
    register_setting('svw_settings_group', 'svw_yandex_metrika_button_click');

    add_settings_section(
        'svw_section_main',
        __('Основные настройки', 'sticky-video-widget'),
        null,
        'sticky-video-widget'
    );

    add_settings_section(
        'svw_section_yandex_metrika',
        __('Настройки Яндекс.Метрики', 'sticky-video-widget'),
        'svw_render_yandex_metrika_section',
        'sticky-video-widget'
    );

    add_settings_field(
        'svw_widget_enabled',
        __('Включить виджет', 'sticky-video-widget'),
        'svw_render_enabled_field',
        'sticky-video-widget',
        'svw_section_main'
    );

    add_settings_field(
        'svw_video_url',
        __('URL видео из медиатеки', 'sticky-video-widget'),
        'svw_render_video_field',
        'sticky-video-widget',
        'svw_section_main'
    );



    add_settings_field(
        'svw_button_text',
        __('Текст кнопки', 'sticky-video-widget'),
        'svw_render_button_text_field',
        'sticky-video-widget',
        'svw_section_main'
    );

    add_settings_field(
        'svw_button_link',
        __('Ссылка кнопки', 'sticky-video-widget'),
        'svw_render_button_link_field',
        'sticky-video-widget',
        'svw_section_main'
    );

    add_settings_field(
        'svw_show_on_mobile',
        __('Показывать на мобильных', 'sticky-video-widget'),
        'svw_render_mobile_field',
        'sticky-video-widget',
        'svw_section_main'
    );

    add_settings_field(
        'svw_autoplay',
        __('Автозапуск видео', 'sticky-video-widget'),
        'svw_render_autoplay_field',
        'sticky-video-widget',
        'svw_section_main'
    );

    add_settings_field(
        'svw_yandex_metrika_counter_id',
        __('ID счетчика Яндекс.Метрики', 'sticky-video-widget'),
        'svw_render_yandex_metrika_counter_id_field',
        'sticky-video-widget',
        'svw_section_yandex_metrika'
    );

    add_settings_field(
        'svw_yandex_metrika_widget_open',
        __('Идентификатор Яндекс.Метрики (открытие)', 'sticky-video-widget'),
        'svw_render_yandex_metrika_widget_open_field',
        'sticky-video-widget',
        'svw_section_yandex_metrika'
    );

    add_settings_field(
        'svw_yandex_metrika_button_click',
        __('Идентификатор Яндекс.Метрики (клик по кнопке)', 'sticky-video-widget'),
        'svw_render_yandex_metrika_button_click_field',
        'sticky-video-widget',
        'svw_section_yandex_metrika'
    );
}
add_action('admin_init', 'svw_register_settings');

// Описание секции Яндекс.Метрики
function svw_render_yandex_metrika_section() {
    ?>
    <p><?php _e('Настройте отправку событий в Яндекс.Метрику для отслеживания взаимодействий с виджетом.', 'sticky-video-widget'); ?></p>
    <p><strong><?php _e('Важно:', 'sticky-video-widget'); ?></strong> <?php _e('На вашем сайте должна быть установлена Яндекс.Метрика для корректной работы событий.', 'sticky-video-widget'); ?></p>
    <?php
}

// Поле для ID счетчика Яндекс.Метрики
function svw_render_yandex_metrika_counter_id_field() {
    $value = get_option('svw_yandex_metrika_counter_id', '');
    ?>
    <input type="text" name="svw_yandex_metrika_counter_id" value="<?php echo esc_attr($value); ?>" placeholder="87971751" />
    <p class="description"><?php _e('ID вашего счетчика Яндекс.Метрики. Например: 87971751', 'sticky-video-widget'); ?></p>
    <?php
}

// Поле включения виджета
function svw_render_enabled_field() {
    $value = get_option('svw_widget_enabled', '1');
    ?>
    <label>
        <input type="checkbox" id="svw_widget_enabled" name="svw_widget_enabled" value="1" <?php checked($value, '1'); ?> />
        <?php _e('Включить отображение виджета на сайте', 'sticky-video-widget'); ?>
    </label>
    <?php
}

// Поле выбора видео
function svw_render_video_field() {
    $value = get_option('svw_video_url');
    ?>
    <input type="text" id="svw_video_url" name="svw_video_url" value="<?php echo esc_attr($value); ?>" style="width: 400px;" />
    <button type="button" id="svw_select_button" class="button">Выбрать видео</button>
    <button type="button" id="svw_clear_button" class="button">Очистить</button>
    <p class="description"><?php _e('Выберите видео из медиатеки WordPress', 'sticky-video-widget'); ?></p>
    <?php
}



// Поле текста кнопки
function svw_render_button_text_field() {
    $value = get_option('svw_button_text', 'Получить КП');
    ?>
    <input type="text" id="svw_button_text" name="svw_button_text" value="<?php echo esc_attr($value); ?>" />
    <p class="description"><?php _e('Текст, который будет отображаться на кнопке', 'sticky-video-widget'); ?></p>
    <?php
}

// Поле ссылки кнопки
function svw_render_button_link_field() {
    $value = get_option('svw_button_link', '#section-price');
    ?>
    <input type="url" name="svw_button_link" value="<?php echo esc_attr($value); ?>" style="width: 400px;" />
    <p class="description"><?php _e('URL или якорь (#section-name), куда будет вести кнопка', 'sticky-video-widget'); ?></p>
    <?php
}

// Поле показа на мобильных
function svw_render_mobile_field() {
    $value = get_option('svw_show_on_mobile', '1');
    ?>
    <label>
        <input type="checkbox" name="svw_show_on_mobile" value="1" <?php checked($value, '1'); ?> />
        <?php _e('Показывать виджет на мобильных устройствах', 'sticky-video-widget'); ?>
    </label>
    <?php
}

// Поле автозапуска
function svw_render_autoplay_field() {
    $value = get_option('svw_autoplay', '1');
    ?>
    <label>
        <input type="checkbox" name="svw_autoplay" value="1" <?php checked($value, '1'); ?> />
        <?php _e('Автоматически запускать видео при загрузке страницы', 'sticky-video-widget'); ?>
    </label>
    <?php
}

// Поле для идентификатора события открытия виджета
function svw_render_yandex_metrika_widget_open_field() {
    $value = get_option('svw_yandex_metrika_widget_open', '');
    ?>
    <input type="text" name="svw_yandex_metrika_widget_open" value="<?php echo esc_attr($value); ?>" placeholder="widget_open" />
    <p class="description"><?php _e('Идентификатор события для отправки в Яндекс.Метрику при открытии виджета. Например: widget_open', 'sticky-video-widget'); ?></p>
    <?php
}

// Поле для идентификатора события клика по кнопке
function svw_render_yandex_metrika_button_click_field() {
    $value = get_option('svw_yandex_metrika_button_click', '');
    ?>
    <input type="text" name="svw_yandex_metrika_button_click" value="<?php echo esc_attr($value); ?>" placeholder="button_click" />
    <p class="description"><?php _e('Идентификатор события для отправки в Яндекс.Метрику при клике на кнопку виджета. Например: button_click', 'sticky-video-widget'); ?></p>
    <?php
}

// Страница настроек
function svw_render_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php _e('Настройки Sticky Video Widget', 'sticky-video-widget'); ?></h1>
        
        <?php if (isset($_GET['settings-updated'])): ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('Настройки сохранены!', 'sticky-video-widget'); ?></p>
            </div>
        <?php endif; ?>
        
        <div class="svw-admin-container">
            <div class="svw-settings-section">
                <form method="post" action="options.php">
                    <?php
                    settings_fields('svw_settings_group');
                    do_settings_sections('sticky-video-widget');
                    submit_button(__('Сохранить настройки', 'sticky-video-widget'));
                    ?>
                </form>
            </div>
            
            <div class="svw-preview-section">
                <h3><?php _e('🎬 Предварительный просмотр', 'sticky-video-widget'); ?></h3>
                <div class="svw-preview-container">
                    <div class="svw-preview-screen">
                        <div class="svw-preview-content">
                            <h4><?php _e('Ваш сайт', 'sticky-video-widget'); ?></h4>
                            <p><?php _e('Содержимое страницы...', 'sticky-video-widget'); ?></p>
                            <div class="svw-preview-text">
                                <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit...</p>
                                <p>Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua...</p>
                            </div>
                        </div>
                        
                        <!-- Живой превью виджета -->
                        <div id="svw-preview-widget" class="svw-preview-widget" data-position="bottom-left">
                            <div class="svw-preview-video">
                                <div class="svw-preview-video-placeholder">
                                    <span class="dashicons dashicons-video-alt3"></span>
                                    <span><?php _e('Видео', 'sticky-video-widget'); ?></span>
                                </div>
                                <video id="svw-preview-video-element" class="svw-preview-video-real" loop muted playsinline style="display: none;">
                                </video>
                            </div>
                            <div class="svw-preview-button">
                                <span id="svw-preview-button-text"><?php _e('Получить КП', 'sticky-video-widget'); ?></span>
                            </div>
                            <button class="svw-preview-close">&times;</button>
                        </div>
                    </div>
                    
                    <div class="svw-preview-controls">
                        <button type="button" id="svw-preview-demo" class="button button-secondary">
                            <?php _e('🎬 Демо виджета', 'sticky-video-widget'); ?>
                        </button>
                        <button type="button" id="svw-preview-reset" class="button">
                            <?php _e('🔄 Сбросить превью', 'sticky-video-widget'); ?>
                        </button>
                    </div>
                </div>
                
                <div class="svw-instructions">
                    <h4><?php _e('Инструкция:', 'sticky-video-widget'); ?></h4>
                                         <ol>
                         <li><?php _e('Включите виджет', 'sticky-video-widget'); ?></li>
                         <li><?php _e('Выберите видео из медиатеки', 'sticky-video-widget'); ?></li>
                         <li><?php _e('Укажите текст и ссылку для кнопки', 'sticky-video-widget'); ?></li>
                         <li><?php _e('Сохраните настройки', 'sticky-video-widget'); ?></li>
                     </ol>
                    
                    <p><strong><?php _e('Совет:', 'sticky-video-widget'); ?></strong> <?php _e('Используйте короткие видео (до 30 секунд) для лучшего пользовательского опыта.', 'sticky-video-widget'); ?></p>
                    
                    <p><strong><?php _e('Превью:', 'sticky-video-widget'); ?></strong> <?php _e('Превью точно копирует поведение виджета. Кликните по видео для раскрытия, по ❌ для закрытия (восстановится через 2 сек).', 'sticky-video-widget'); ?></p>
                </div>
                
                <div class="svw-author-info">
                    <p><?php _e('Разработано', 'sticky-video-widget'); ?> <a href="https://mitroliti.com" target="_blank" style="color: #0073aa; text-decoration: none;"><strong>Mitroliti</strong></a></p>
                    <!-- <p><a href="https://mitroliti.com/plugins" target="_blank" style="color: #0073aa; text-decoration: none;"><//?php _e('Больше полезных плагинов', 'sticky-video-widget'); ?></a></p> -->
                </div>
            </div>
        </div>
    </div>
    
    
    <script>
    jQuery(document).ready(function($) {
        // Обновление превью в реальном времени
        function updatePreview() {
            const enabled = $('#svw_widget_enabled').is(':checked');
            const buttonText = $('#svw_button_text').val() || '<?php _e('Получить КП', 'sticky-video-widget'); ?>';
            const videoUrl = $('#svw_video_url').val();
            
            const widget = $('#svw-preview-widget');
            const placeholder = $('.svw-preview-video-placeholder');
            const videoElement = $('#svw-preview-video-element');
            
            // Показать/скрыть виджет
            if (enabled) {
                widget.show();
            } else {
                widget.hide();
            }
            
            // Обновить текст кнопки
            $('#svw-preview-button-text').text(buttonText);
            
            // Обновить видео/заглушку
            if (videoUrl) {
                // Показываем реальное видео
                videoElement.attr('src', videoUrl);
                videoElement.show();
                placeholder.hide();
                
                // Запускаем видео при загрузке
                videoElement[0].load();
                videoElement[0].play().catch(e => console.log('Preview autoplay prevented:', e));
            } else {
                // Показываем заглушку
                videoElement.hide();
                placeholder.show();
                placeholder.html('<span class="dashicons dashicons-video-alt3"></span><span><?php _e('Выберите видео', 'sticky-video-widget'); ?></span>');
                placeholder.css('color', '#666');
            }
        }
        
        // Экспортируем функцию для использования в admin-scripts.js
        window.updateSVWPreview = updatePreview;
        
        // Слушатели изменений
        $('#svw_widget_enabled, #svw_button_text, #svw_video_url').on('change input', updatePreview);
        
        // Кнопка демо - переключает состояние виджета (открыть/закрыть)
        $('#svw-preview-demo').click(function() {
            const widget = $('#svw-preview-widget');
            const videoElement = $('#svw-preview-video-element');
            
            if (widget.hasClass('svw-preview-opened')) {
                // Закрываем виджет
                widget.removeClass('svw-preview-opened');
                if (videoElement[0] && videoElement[0].src) {
                    videoElement[0].muted = true;
                }
            } else {
                // Открываем виджет
                widget.addClass('svw-preview-opened');
                if (videoElement[0] && videoElement[0].src) {
                    videoElement[0].currentTime = 0;
                    videoElement[0].muted = false;
                    videoElement[0].play().catch(e => console.log('Preview play prevented:', e));
                }
            }
        });
        
        // Кнопка сброса - сворачивает виджет и сбрасывает превью
        $('#svw-preview-reset').click(function() {
            const widget = $('#svw-preview-widget');
            const videoElement = $('#svw-preview-video-element');
            
            // Закрываем виджет
            widget.removeClass('svw-preview-opened');
            if (videoElement[0] && videoElement[0].src) {
                videoElement[0].muted = true;
                videoElement[0].currentTime = 0;
            }
            
            // Обновляем превью
            updatePreview();
        });
        
        // Клик по превью виджета - работает как настоящий
        $('#svw-preview-widget .svw-preview-video').click(function(e) {
            e.preventDefault();
            $('#svw-preview-demo').click();
        });
        
        // Кнопка закрытия в превью
        $('#svw-preview-widget .svw-preview-close').click(function(e) {
            e.preventDefault();
            e.stopPropagation();
            const widget = $('#svw-preview-widget');
            widget.hide();
            
            // Через 2 секунды показываем снова для демонстрации
            setTimeout(() => {
                widget.show().removeClass('svw-preview-opened');
            }, 2000);
        });
        
        // Инициализация
        setTimeout(updatePreview, 100);
    });
    </script>
    
    <style>
        .svw-admin-container {
            display: flex;
            gap: 30px;
            margin-top: 20px;
        }
        
        .svw-settings-section {
            flex: 2;
        }
        
        .svw-preview-section {
            flex: 1;
            background: #fff;
            padding: 20px;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        
        .svw-preview-container {
            margin: 15px 0;
        }
        
        .svw-preview-screen {
            position: relative;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 8px;
            height: 600px;
            overflow: hidden;
            margin-bottom: 15px;
        }
        
        .svw-preview-content {
            padding: 20px;
            color: white;
        }
        
        .svw-preview-content h4 {
            margin: 0 0 10px 0;
            color: white;
            font-size: 18px;
        }
        
        .svw-preview-text {
            margin-top: 15px;
            opacity: 0.8;
            font-size: 12px;
            line-height: 1.4;
        }
        
        .svw-preview-widget {
            position: absolute;
            width: 130px;
            height: 180px;
            background: rgb(238, 238, 238);
            border: 5px solid rgb(255, 255, 255);
            border-radius: 20px;
            box-shadow: rgba(0, 0, 0, 0.2) 0px 10px 20px;
            cursor: pointer;
            transition: width 0.3s ease-in-out, height 0.3s ease-in-out, 
                       border-color 0.2s ease-in-out, transform 0.2s ease-in-out;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-sizing: border-box;
            user-select: none;
        }
        
        .svw-preview-widget:hover {
            transform: scale(1.05) translate(5px, -5px);
            border-color: rgb(19, 19, 68);
        }
        
        .svw-preview-widget.svw-preview-opened {
            width: 280px;
            height: 500px;
            border-radius: 20px;
            border-color: rgb(255, 255, 255);
            transform: scale(1.1);
        }
        
        /* Позиция виджета - фиксированная внизу слева */
        .svw-preview-widget {
            bottom: 50px;
            left: 50px;
        }
        
        .svw-preview-video {
            flex: 1;
            background: #000;
            border-radius: 15px 15px 0 0;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .svw-preview-video-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: #666;
            font-size: 14px;
            text-align: center;
        }
        
        .svw-preview-video-placeholder .dashicons {
            font-size: 32px;
            margin-bottom: 8px;
        }
        
        .svw-preview-video-real {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 100%;
            height: 100%;
            min-width: 100%;
            min-height: 100%;
            object-fit: cover;
            z-index: 200;
            transition: opacity 0.4s ease-in-out;
            opacity: 0.8;
        }
        
        .svw-preview-button {
            position: absolute;
            bottom: 20px;
            right: 20px;
            left: 20px;
            height: 65px;
            border-radius: 10px;
            z-index: 300;
            box-shadow: rgba(0, 0, 0, 0.25) 0px 4px 15px;
            text-align: center;
            transition: opacity 0.3s ease-in-out, background-color 0.2s ease-in-out,
                       transform 0.2s ease-in-out, visibility 0.3s ease-in-out;
            opacity: 0;
            visibility: hidden;
            background-color: rgb(253, 216, 42);
            font-size: 14px;
            font-family: Helvetica;
            color: #000000;
            vertical-align: middle;
            line-height: 65px;
            text-transform: uppercase;
            font-weight: normal;
            text-decoration: none;
            display: block;
        }
        
        .svw-preview-button:hover {
            background-color: rgb(255, 226, 87);
            text-decoration: none;
        }
        
        .svw-preview-widget.svw-preview-opened .svw-preview-button {
            opacity: 1;
            visibility: visible;
        }
        
        .svw-preview-close {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 20px;
            height: 20px;
            z-index: 250;
            opacity: 0;
            transition: opacity 0.2s ease-in-out, transform 0.3s ease-in-out;
            background: none;
            border: none;
            cursor: pointer;
        }
        
        .svw-preview-close:before,
        .svw-preview-close:after {
            position: absolute;
            left: 9px;
            top: 1px;
            content: " ";
            height: 18px;
            width: 2px;
            background: white;
            box-shadow: rgba(0, 0, 0, 0.5) 1px 1px 10px;
        }
        
        .svw-preview-close:before {
            transform: rotate(45deg);
        }
        
        .svw-preview-close:after {
            transform: rotate(-45deg);
        }
        
        .svw-preview-widget:hover .svw-preview-close {
            opacity: 0.5;
        }
        
        .svw-preview-widget.svw-preview-opened .svw-preview-close {
            opacity: 0.5;
        }
        
        .svw-preview-widget.svw-preview-opened .svw-preview-close:before {
            display: none;
        }
        
        .svw-preview-widget.svw-preview-opened .svw-preview-close:after {
            transform: rotate(90deg);
        }
        
        .svw-preview-widget.svw-preview-opened .svw-preview-close:hover {
            opacity: 1;
        }
        
        .svw-preview-controls {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .svw-preview-controls button {
            margin: 0 5px;
            font-size: 13px;
            padding: 8px 16px;
        }
        
        .svw-instructions {
            margin: 20px 0;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .svw-instructions h4 {
            margin-top: 0;
        }
        
        .svw-instructions ol {
            font-size: 13px;
        }
        
        .svw-author-info {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            color: #666;
            font-size: 12px;
        }
        
        @media (max-width: 1200px) {
            .svw-admin-container {
                flex-direction: column;
            }
        }
        
        /* Адаптивные стили для превью виджета - как в реальном */
        @media only screen and (max-width: 1023px) {
            .svw-preview-close {
                opacity: 0.5;
            }
        }
        
        @media only screen and (max-width: 768px) {
            .svw-preview-widget {
                left: 15px !important;
                bottom: 15px !important;
                width: 90px;
                height: 125px;
            }
            
            .svw-preview-widget.svw-preview-opened {
                width: 280px !important;
                height: 500px !important;
                left: 15px !important;
                bottom: 15px !important;
            }
        }
        
        .form-table th {
            width: 200px;
        }
        .form-table td input[type="text"],
        .form-table td input[type="url"] {
            width: 100%;
            max-width: 400px;
        }
    </style>
    <?php
}

// Вывод виджета во фронте
function svw_render_frontend_widget() {
    // Проверяем, включен ли виджет
    if (!get_option('svw_widget_enabled', '1')) {
        return;
    }

    $video_url = esc_url(get_option('svw_video_url'));
    if (!$video_url) {
        return;
    }

    // Получаем настройки
    $button_text = get_option('svw_button_text', 'Получить КП');
    $button_link = get_option('svw_button_link', '#section-price');
    $show_on_mobile = get_option('svw_show_on_mobile', '1');
    $autoplay = get_option('svw_autoplay', '1');

    // CSS класс для мобильных устройств
    $mobile_class = $show_on_mobile ? '' : 'svw-hide-mobile';
    
    ?>
    <div class="video-widget <?php echo esc_attr($mobile_class); ?>" data-state="default">
        <div class="video-widget__container">
            <video id="video-widget__video" 
                   loop 
                   <?php echo $autoplay ? 'autoplay' : ''; ?> 
                   playsinline 
                   preload="auto" 
                   muted 
                   controlslist="nodownload" 
                   disablepictureinpicture 
                   class="video-widget__video" 
                   src="<?php echo $video_url; ?>">
            </video>
            <button class="video-widget__close" aria-label="<?php esc_attr_e('Закрыть видео', 'sticky-video-widget'); ?>"></button>
            <a class="video-widget__button" href="<?php echo esc_url($button_link); ?>">
                <?php echo esc_html($button_text); ?>
            </a>
        </div>
    </div>
    <?php
}
add_action('wp_footer', 'svw_render_frontend_widget');

// Удаление опций при деактивации
function svw_deactivate_plugin() {
    delete_option('svw_video_url');
    delete_option('svw_button_text');
    delete_option('svw_button_link');

    delete_option('svw_widget_enabled');
    delete_option('svw_show_on_mobile');
    delete_option('svw_autoplay');
    delete_option('svw_yandex_metrika_counter_id');
    delete_option('svw_yandex_metrika_widget_open');
    delete_option('svw_yandex_metrika_button_click');
}
register_deactivation_hook(__FILE__, 'svw_deactivate_plugin');
