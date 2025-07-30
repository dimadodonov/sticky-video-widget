<?php
/*
Plugin Name: Sticky Video Widget
Description: Добавляет на сайт настраиваемый плавающий видео-виджет с возможностью выбора видео из медиатеки, настройкой позиции, текста кнопки и других параметров.
Version: 1.1.0
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
    wp_enqueue_style('svw_styles', plugin_dir_url(__FILE__) . 'styles.css', array(), '1.1.0');
    
    if (!is_admin()) {
        wp_enqueue_script('svw_scripts', plugin_dir_url(__FILE__) . 'scripts.js', array(), '1.1.0', true);
        
        // Передаем настройки в JavaScript
        $settings = array(
            'autoplay' => get_option('svw_autoplay', '1'),
            'position' => get_option('svw_widget_position', 'bottom-left')
        );
        wp_localize_script('svw_scripts', 'svwSettings', $settings);
    }

    if ($hook === 'settings_page_sticky-video-widget') {
        wp_enqueue_media();
        wp_enqueue_script('svw_admin_scripts', plugin_dir_url(__FILE__) . 'admin-scripts.js', array('jquery'), '1.1.0', true);
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
    register_setting('svw_settings_group', 'svw_widget_position');
    register_setting('svw_settings_group', 'svw_widget_enabled');
    register_setting('svw_settings_group', 'svw_show_on_mobile');
    register_setting('svw_settings_group', 'svw_autoplay');

    add_settings_section(
        'svw_section_main',
        __('Основные настройки', 'sticky-video-widget'),
        null,
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
        'svw_widget_position',
        __('Позиция виджета', 'sticky-video-widget'),
        'svw_render_position_field',
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
}
add_action('admin_init', 'svw_register_settings');

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

// Поле позиции виджета
function svw_render_position_field() {
    $value = get_option('svw_widget_position', 'bottom-left');
    $positions = array(
        'bottom-left' => __('Внизу слева', 'sticky-video-widget'),
        'bottom-right' => __('Внизу справа', 'sticky-video-widget'),
        'top-left' => __('Вверху слева', 'sticky-video-widget'),
        'top-right' => __('Вверху справа', 'sticky-video-widget')
    );
    ?>
    <select id="svw_widget_position" name="svw_widget_position">
        <?php foreach ($positions as $key => $label): ?>
            <option value="<?php echo esc_attr($key); ?>" <?php selected($value, $key); ?>>
                <?php echo esc_html($label); ?>
            </option>
        <?php endforeach; ?>
    </select>
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
                            </div>
                            <div class="svw-preview-button">
                                <span id="svw-preview-button-text"><?php _e('Получить КП', 'sticky-video-widget'); ?></span>
                            </div>
                            <button class="svw-preview-close">&times;</button>
                        </div>
                    </div>
                    
                    <div class="svw-preview-controls">
                        <button type="button" id="svw-preview-demo" class="button button-secondary">
                            <?php _e('🎭 Демо взаимодействия', 'sticky-video-widget'); ?>
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
                        <li><?php _e('Настройте позицию и внешний вид', 'sticky-video-widget'); ?></li>
                        <li><?php _e('Укажите текст и ссылку для кнопки', 'sticky-video-widget'); ?></li>
                        <li><?php _e('Сохраните настройки', 'sticky-video-widget'); ?></li>
                    </ol>
                    
                    <p><strong><?php _e('Совет:', 'sticky-video-widget'); ?></strong> <?php _e('Используйте короткие видео (до 30 секунд) для лучшего пользовательского опыта.', 'sticky-video-widget'); ?></p>
                </div>
                
                <div class="svw-author-info">
                    <p><?php _e('Разработано', 'sticky-video-widget'); ?> <a href="https://mitroliti.com" target="_blank" style="color: #0073aa; text-decoration: none;"><strong>Mitroliti</strong></a></p>
                    <p><a href="https://mitroliti.com/plugins" target="_blank" style="color: #0073aa; text-decoration: none;"><?php _e('Больше полезных плагинов', 'sticky-video-widget'); ?></a></p>
                </div>
            </div>
        </div>
    </div>
    
    
    <script>
    jQuery(document).ready(function($) {
        // Обновление превью в реальном времени
        function updatePreview() {
            const enabled = $('#svw_widget_enabled').is(':checked');
            const position = $('#svw_widget_position').val() || 'bottom-left';
            const buttonText = $('#svw_button_text').val() || '<?php _e('Получить КП', 'sticky-video-widget'); ?>';
            const videoUrl = $('#svw_video_url').val();
            
            const widget = $('#svw-preview-widget');
            const placeholder = $('.svw-preview-video-placeholder');
            
            // Показать/скрыть виджет
            if (enabled) {
                widget.show();
            } else {
                widget.hide();
            }
            
            // Обновить позицию
            widget.removeClass('svw-pos-top-left svw-pos-top-right svw-pos-bottom-left svw-pos-bottom-right')
                  .addClass('svw-pos-' + position);
            
            // Обновить текст кнопки
            $('#svw-preview-button-text').text(buttonText);
            
            // Обновить индикатор видео
            if (videoUrl) {
                placeholder.html('<span class="dashicons dashicons-yes-alt"></span><span><?php _e('Видео выбрано', 'sticky-video-widget'); ?></span>');
                placeholder.css('color', '#46b450');
            } else {
                placeholder.html('<span class="dashicons dashicons-video-alt3"></span><span><?php _e('Выберите видео', 'sticky-video-widget'); ?></span>');
                placeholder.css('color', '#666');
            }
        }
        
        // Экспортируем функцию для использования в admin-scripts.js
        window.updateSVWPreview = updatePreview;
        
        // Слушатели изменений
        $('#svw_widget_enabled, #svw_widget_position, #svw_button_text, #svw_video_url').on('change input', updatePreview);
        
        // Демо взаимодействия
        $('#svw-preview-demo').click(function() {
            const widget = $('#svw-preview-widget');
            widget.addClass('svw-preview-opened');
            
            setTimeout(() => {
                widget.removeClass('svw-preview-opened');
            }, 3000);
        });
        
        // Сброс превью
        $('#svw-preview-reset').click(function() {
            $('#svw-preview-widget').removeClass('svw-preview-opened');
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
            height: 300px;
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
            width: 80px;
            height: 100px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }
        
        .svw-preview-widget:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 25px rgba(0,0,0,0.2);
        }
        
        .svw-preview-widget.svw-preview-opened {
            width: 160px;
            height: 200px;
            transform: scale(1.1);
        }
        
        /* Позиции виджета */
        .svw-preview-widget.svw-pos-top-left {
            top: 15px;
            left: 15px;
        }
        
        .svw-preview-widget.svw-pos-top-right {
            top: 15px;
            right: 15px;
        }
        
        .svw-preview-widget.svw-pos-bottom-left {
            bottom: 15px;
            left: 15px;
        }
        
        .svw-preview-widget.svw-pos-bottom-right {
            bottom: 15px;
            right: 15px;
        }
        
        .svw-preview-video {
            flex: 1;
            background: #000;
            border-radius: 8px 8px 0 0;
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
            font-size: 10px;
            text-align: center;
        }
        
        .svw-preview-video-placeholder .dashicons {
            font-size: 20px;
            margin-bottom: 2px;
        }
        
        .svw-preview-button {
            background: #fdd82a;
            color: #000;
            padding: 8px 12px;
            text-align: center;
            font-size: 10px;
            font-weight: 600;
            border-radius: 0 0 8px 8px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            text-transform: uppercase;
        }
        
        .svw-preview-close {
            position: absolute;
            top: 5px;
            right: 5px;
            background: rgba(0,0,0,0.5);
            color: white;
            border: none;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            cursor: pointer;
            font-size: 12px;
            line-height: 1;
            display: none;
        }
        
        .svw-preview-widget.svw-preview-opened .svw-preview-close {
            display: block;
        }
        
        .svw-preview-controls {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .svw-preview-controls button {
            margin: 0 5px;
            font-size: 12px;
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
    $position = get_option('svw_widget_position', 'bottom-left');
    $button_text = get_option('svw_button_text', 'Получить КП');
    $button_link = get_option('svw_button_link', '#section-price');
    $show_on_mobile = get_option('svw_show_on_mobile', '1');
    $autoplay = get_option('svw_autoplay', '1');

    // CSS класс для позиции
    $position_class = 'svw-' . $position;
    
    // CSS класс для мобильных устройств
    $mobile_class = $show_on_mobile ? '' : 'svw-hide-mobile';
    
    ?>
    <div class="video-widget <?php echo esc_attr($position_class . ' ' . $mobile_class); ?>" data-state="default">
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
    delete_option('svw_widget_position');
    delete_option('svw_widget_enabled');
    delete_option('svw_show_on_mobile');
    delete_option('svw_autoplay');
}
register_deactivation_hook(__FILE__, 'svw_deactivate_plugin');
