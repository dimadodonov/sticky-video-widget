<?php
/*
Plugin Name: Sticky Video Widget
Description: Добавляет на сайт настраиваемый плавающий видео-виджет с возможностью выбора видео из медиатеки, настройкой позиции, текста кнопки и других параметров.
Version: 1.1.0
Author: Dmitry Dodonov
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
        <input type="checkbox" name="svw_widget_enabled" value="1" <?php checked($value, '1'); ?> />
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
    <select name="svw_widget_position">
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
    <input type="text" name="svw_button_text" value="<?php echo esc_attr($value); ?>" />
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
        
        <div style="display: flex; gap: 20px;">
            <div style="flex: 2;">
                <form method="post" action="options.php">
                    <?php
                    settings_fields('svw_settings_group');
                    do_settings_sections('sticky-video-widget');
                    submit_button(__('Сохранить настройки', 'sticky-video-widget'));
                    ?>
                </form>
            </div>
            
            <div style="flex: 1; background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">
                <h3><?php _e('Предварительный просмотр', 'sticky-video-widget'); ?></h3>
                <p><?php _e('Сохраните настройки и посетите любую страницу сайта, чтобы увидеть виджет в действии.', 'sticky-video-widget'); ?></p>
                
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
        </div>
    </div>
    
    <style>
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
