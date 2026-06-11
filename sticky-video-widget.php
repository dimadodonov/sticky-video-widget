<?php
/*
Plugin Name: Sticky Video Widget
Description: Добавляет на сайт настраиваемый плавающий видео-виджет с возможностью выбора видео из медиатеки, настройкой позиции, текста кнопки и интеграцией с Яндекс.Метрикой.
Version: 1.3.0
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
    wp_enqueue_style('svw_styles', plugin_dir_url(__FILE__) . 'styles.css', array(), '1.3.0');

    if (!is_admin()) {
        wp_enqueue_script('svw_scripts', plugin_dir_url(__FILE__) . 'scripts.js', array(), '1.3.0', true);

        // Передаем настройки в JavaScript
        $settings = array(
            'autoplay'                    => get_option('svw_autoplay', '1'),
            'appearance_delay'            => (int) get_option('svw_appearance_delay', '0'),
            'button_trigger_selector'     => get_option('svw_button_trigger_selector', ''),
            'yandex_metrika_counter_id'   => get_option('svw_yandex_metrika_counter_id', ''),
            'yandex_metrika_widget_open'  => get_option('svw_yandex_metrika_widget_open', ''),
            'yandex_metrika_button_click' => get_option('svw_yandex_metrika_button_click', ''),
        );
        wp_localize_script('svw_scripts', 'svwSettings', $settings);
    }

    if ($hook === 'settings_page_sticky-video-widget') {
        wp_enqueue_media();

        // Select2 для выбора страниц в правилах отображения
        if (!wp_script_is('select2', 'registered')) {
            wp_register_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0');
            wp_register_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), '4.1.0', true);
        }
        wp_enqueue_style('select2');
        wp_enqueue_script('select2');

        wp_enqueue_script('svw_admin_scripts', plugin_dir_url(__FILE__) . 'admin-scripts.js', array('jquery', 'select2'), '1.3.0', true);
        wp_localize_script('svw_admin_scripts', 'svwAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('svw_search_posts_nonce'),
        ));
    }

    // Медиапикеры в редакторе записей/страниц (для meta box)
    if (in_array($hook, array('post.php', 'post-new.php'))) {
        wp_enqueue_script('svw_admin_scripts', plugin_dir_url(__FILE__) . 'admin-scripts.js', array('jquery'), '1.3.0', true);
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
    register_setting('svw_settings_group', 'svw_button_trigger_selector', array('sanitize_callback' => 'sanitize_text_field'));

    register_setting('svw_settings_group', 'svw_widget_enabled');
    register_setting('svw_settings_group', 'svw_show_on_mobile');
    register_setting('svw_settings_group', 'svw_autoplay');
    register_setting('svw_settings_group', 'svw_yandex_metrika_counter_id');
    register_setting('svw_settings_group', 'svw_yandex_metrika_widget_open');
    register_setting('svw_settings_group', 'svw_yandex_metrika_button_click');
    register_setting('svw_settings_group', 'svw_video_poster', array('sanitize_callback' => 'esc_url_raw'));
    register_setting('svw_settings_group', 'svw_appearance_delay', array('sanitize_callback' => 'absint'));
    register_setting('svw_settings_group', 'svw_display_mode', array('sanitize_callback' => 'sanitize_text_field'));
    register_setting('svw_settings_group', 'svw_display_pages', array('sanitize_callback' => 'svw_sanitize_ids_array'));
    register_setting('svw_settings_group', 'svw_display_post_types', array('sanitize_callback' => 'svw_sanitize_post_types_array'));

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

    add_settings_section(
        'svw_section_display_rules',
        __('Правила отображения', 'sticky-video-widget'),
        'svw_render_display_rules_section',
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
        'svw_video_poster',
        __('Постер видео', 'sticky-video-widget'),
        'svw_render_poster_field',
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
        'svw_button_trigger_selector',
        __('Селектор для открытия попапа', 'sticky-video-widget'),
        'svw_render_button_trigger_selector_field',
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
        'svw_appearance_delay',
        __('Задержка появления', 'sticky-video-widget'),
        'svw_render_appearance_delay_field',
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

    add_settings_field(
        'svw_display_mode',
        __('Режим отображения', 'sticky-video-widget'),
        'svw_render_display_mode_field',
        'sticky-video-widget',
        'svw_section_display_rules'
    );

    add_settings_field(
        'svw_display_pages',
        __('Выбор страниц', 'sticky-video-widget'),
        'svw_render_display_pages_field',
        'sticky-video-widget',
        'svw_section_display_rules'
    );

    add_settings_field(
        'svw_display_post_types',
        __('Типы записей', 'sticky-video-widget'),
        'svw_render_display_post_types_field',
        'sticky-video-widget',
        'svw_section_display_rules'
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
    <p class="description"><?php _e('URL или якорь (#section-name), куда будет вести кнопка. Игнорируется, если задан «Селектор для открытия попапа» ниже.', 'sticky-video-widget'); ?></p>
    <?php
}

// Поле CSS-селектора для попапа
function svw_render_button_trigger_selector_field() {
    $value = get_option('svw_button_trigger_selector', '');
    ?>
    <input type="text" id="svw_button_trigger_selector" name="svw_button_trigger_selector"
           value="<?php echo esc_attr($value); ?>"
           placeholder=".btn_modal_callback"
           style="width: 400px;" />
    <p class="description">
        <?php _e('CSS-селектор элемента на странице, который нужно кликнуть для открытия попап-окна. Например:', 'sticky-video-widget'); ?>
        <code>.btn_modal_callback</code> <?php _e('или', 'sticky-video-widget'); ?> <code>#open-modal</code>.<br>
        <?php _e('Если поле заполнено — при клике на кнопку виджет свернётся и откроется попап. Поле «Ссылка кнопки» при этом игнорируется.', 'sticky-video-widget'); ?>
    </p>
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

// Вспомогательные функции санитизации для массивов
function svw_sanitize_ids_array($input) {
    if (empty($input) || !is_array($input)) {
        return array();
    }
    return array_values(array_map('absint', array_filter($input)));
}

function svw_sanitize_post_types_array($input) {
    if (empty($input) || !is_array($input)) {
        return array();
    }
    $allowed = array_keys(get_post_types(array('public' => true)));
    return array_values(array_intersect(array_map('sanitize_key', $input), $allowed));
}

// Секция правил отображения
function svw_render_display_rules_section() {
    ?>
    <p><?php _e('Настройте, на каких страницах виджет должен отображаться. Правила по типам записей и конкретным страницам работают по принципу ИЛИ.', 'sticky-video-widget'); ?></p>
    <p><?php _e('Для точечного переопределения (другое видео или отключение виджета) используйте блок «Sticky Video Widget» в редакторе каждой страницы.', 'sticky-video-widget'); ?></p>
    <?php
}

// Поле режима отображения (all / include / exclude)
function svw_render_display_mode_field() {
    $value = get_option('svw_display_mode', 'all');
    ?>
    <fieldset>
        <label style="display:block;margin-bottom:6px;">
            <input type="radio" name="svw_display_mode" value="all" <?php checked($value, 'all'); ?> />
            <?php _e('Показывать на всех страницах', 'sticky-video-widget'); ?>
        </label>
        <label style="display:block;margin-bottom:6px;">
            <input type="radio" name="svw_display_mode" value="include" <?php checked($value, 'include'); ?> />
            <?php _e('Показывать только на выбранных страницах / типах', 'sticky-video-widget'); ?>
        </label>
        <label style="display:block;">
            <input type="radio" name="svw_display_mode" value="exclude" <?php checked($value, 'exclude'); ?> />
            <?php _e('Скрывать на выбранных страницах / типах', 'sticky-video-widget'); ?>
        </label>
    </fieldset>
    <?php
}

// Поле выбора страниц (Select2 + AJAX)
function svw_render_display_pages_field() {
    $ids = get_option('svw_display_pages', array());
    if (!is_array($ids)) {
        $ids = array();
    }
    ?>
    <select id="svw_display_pages" name="svw_display_pages[]" multiple="multiple" style="width: 400px; min-height: 38px;">
        <?php
        foreach ($ids as $post_id) {
            $post_id = absint($post_id);
            $post = get_post($post_id);
            if ($post) {
                printf(
                    '<option value="%d" selected="selected">%s (ID: %d)</option>',
                    $post->ID,
                    esc_html($post->post_title),
                    $post->ID
                );
            }
        }
        ?>
    </select>
    <p class="description"><?php _e('Начните вводить название страницы или записи (мин. 1 символ). Поддерживается выбор нескольких страниц.', 'sticky-video-widget'); ?></p>
    <?php
}

// Поле выбора типов записей
function svw_render_display_post_types_field() {
    $saved = get_option('svw_display_post_types', array());
    if (!is_array($saved)) {
        $saved = array();
    }
    $post_types = get_post_types(array('public' => true), 'objects');
    foreach ($post_types as $post_type) {
        ?>
        <label style="display:block;margin-bottom:5px;">
            <input type="checkbox"
                   name="svw_display_post_types[]"
                   value="<?php echo esc_attr($post_type->name); ?>"
                   <?php checked(in_array($post_type->name, $saved, true)); ?> />
            <?php echo esc_html($post_type->label); ?> <code><?php echo esc_html($post_type->name); ?></code>
        </label>
        <?php
    }
    ?>
    <p class="description"><?php _e('Оставьте пустым — применять ко всем типам. Работает совместно с выбором конкретных страниц.', 'sticky-video-widget'); ?></p>
    <?php
}

// Поле постера видео (глобальный)
function svw_render_poster_field() {
    $value = get_option('svw_video_poster', '');
    ?>
    <input type="text" id="svw_video_poster" name="svw_video_poster"
           value="<?php echo esc_attr($value); ?>"
           style="width: 400px;" placeholder="https://" />
    <button type="button" id="svw_select_poster_button" class="button"><?php _e('Выбрать постер', 'sticky-video-widget'); ?></button>
    <button type="button" id="svw_clear_poster_button" class="button"><?php _e('Очистить', 'sticky-video-widget'); ?></button>
    <?php if ($value) : ?>
    <br><img id="svw_poster_preview" src="<?php echo esc_url($value); ?>"
             style="max-width:120px;max-height:70px;margin-top:8px;border-radius:4px;display:block;" />
    <?php else : ?>
    <img id="svw_poster_preview" src=""
         style="max-width:120px;max-height:70px;margin-top:8px;border-radius:4px;display:none;" />
    <?php endif; ?>
    <p class="description"><?php _e('Изображение, которое отображается пока видео не загрузилось. Рекомендуется: первый кадр вашего видео.', 'sticky-video-widget'); ?></p>
    <?php
}

// Поле задержки появления виджета
function svw_render_appearance_delay_field() {
    $value = (int) get_option('svw_appearance_delay', '0');
    ?>
    <input type="number" name="svw_appearance_delay"
           value="<?php echo esc_attr($value); ?>"
           min="0" max="60" step="1" style="width: 80px;" />
    <span> <?php _e('секунд', 'sticky-video-widget'); ?></span>
    <p class="description"><?php _e('Задержка перед появлением виджета (0–60 сек). 0 — виджет появляется сразу после загрузки страницы.', 'sticky-video-widget'); ?></p>
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

// Проверка правил отображения виджета
function svw_should_display_widget() {
    $mode = get_option('svw_display_mode', 'all');

    if ($mode === 'all') {
        return true;
    }

    $current_id   = (int) get_the_ID();
    $current_type = (string) get_post_type();

    $page_ids = get_option('svw_display_pages', array());
    if (!is_array($page_ids)) {
        $page_ids = array();
    }
    $page_ids = array_map('absint', array_filter($page_ids));

    $post_types = get_option('svw_display_post_types', array());
    if (!is_array($post_types)) {
        $post_types = array();
    }
    $post_types = array_map('sanitize_key', array_filter($post_types));

    $id_matches   = !empty($page_ids) && in_array($current_id, $page_ids, true);
    $type_matches = !empty($post_types) && in_array($current_type, $post_types, true);
    $any_match    = $id_matches || $type_matches;

    if ($mode === 'include') {
        // Если правила не заданы — не показывать нигде
        if (empty($page_ids) && empty($post_types)) {
            return false;
        }
        return $any_match;
    }

    if ($mode === 'exclude') {
        // Если правила не заданы — показывать везде
        if (empty($page_ids) && empty($post_types)) {
            return true;
        }
        return !$any_match;
    }

    return true;
}

// AJAX: поиск страниц/записей для Select2
function svw_search_posts_ajax() {
    check_ajax_referer('svw_search_posts_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_die('', '', array('response' => 403));
    }

    $search = sanitize_text_field(isset($_GET['q']) ? $_GET['q'] : '');

    $query = new WP_Query(array(
        'post_type'      => 'any',
        'post_status'    => 'publish',
        's'              => $search,
        'posts_per_page' => 20,
        'orderby'        => 'relevance',
        'no_found_rows'  => true,
    ));

    $results = array();
    foreach ($query->posts as $post) {
        $results[] = array(
            'id'   => $post->ID,
            'text' => $post->post_title . ' (' . $post->post_type . ') #' . $post->ID,
        );
    }

    wp_send_json(array('results' => $results));
}
add_action('wp_ajax_svw_search_posts', 'svw_search_posts_ajax');

// Meta box: регистрация
function svw_add_meta_box() {
    add_meta_box(
        'svw_meta_box',
        __('Sticky Video Widget', 'sticky-video-widget'),
        'svw_render_meta_box',
        array('post', 'page'),
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'svw_add_meta_box');

// Meta box: рендер
function svw_render_meta_box($post) {
    wp_nonce_field('svw_meta_box_nonce', 'svw_meta_box_nonce_field');

    $video_url      = get_post_meta($post->ID, '_svw_video_url', true);
    $video_poster   = get_post_meta($post->ID, '_svw_video_poster', true);
    $disable_widget = get_post_meta($post->ID, '_svw_disable_widget', true);
    ?>
    <p>
        <label for="svw_meta_video_url"><strong><?php _e('Видео для этой страницы', 'sticky-video-widget'); ?></strong></label>
        <br>
        <input type="text" id="svw_meta_video_url" name="svw_meta_video_url"
               value="<?php echo esc_attr($video_url); ?>"
               placeholder="<?php esc_attr_e('Оставьте пустым — глобальное видео', 'sticky-video-widget'); ?>"
               style="width:100%;margin-top:4px;" />
        <span style="display:flex;gap:4px;margin-top:4px;">
            <button type="button" class="button button-small svw-meta-select-video"><?php _e('Выбрать', 'sticky-video-widget'); ?></button>
            <button type="button" class="button button-small svw-meta-clear-video"><?php _e('Очистить', 'sticky-video-widget'); ?></button>
        </span>
    </p>
    <p>
        <label for="svw_meta_video_poster"><strong><?php _e('Постер видео', 'sticky-video-widget'); ?></strong></label>
        <br>
        <input type="text" id="svw_meta_video_poster" name="svw_meta_video_poster"
               value="<?php echo esc_attr($video_poster); ?>"
               placeholder="<?php esc_attr_e('Оставьте пустым — глобальный постер', 'sticky-video-widget'); ?>"
               style="width:100%;margin-top:4px;" />
        <span style="display:flex;gap:4px;margin-top:4px;">
            <button type="button" class="button button-small svw-meta-select-poster"><?php _e('Выбрать', 'sticky-video-widget'); ?></button>
            <button type="button" class="button button-small svw-meta-clear-poster"><?php _e('Очистить', 'sticky-video-widget'); ?></button>
        </span>
        <img id="svw_meta_poster_preview"
             src="<?php echo esc_url($video_poster); ?>"
             style="max-width:100%;max-height:60px;margin-top:6px;border-radius:4px;<?php echo $video_poster ? '' : 'display:none;'; ?>" />
    </p>
    <hr>
    <p>
        <label>
            <input type="checkbox" name="svw_meta_disable_widget" value="1" <?php checked($disable_widget, '1'); ?> />
            <strong><?php _e('Отключить виджет на этой странице', 'sticky-video-widget'); ?></strong>
        </label>
    </p>
    <?php
}

// Meta box: сохранение
function svw_save_meta_box($post_id) {
    if (!isset($_POST['svw_meta_box_nonce_field']) ||
        !wp_verify_nonce($_POST['svw_meta_box_nonce_field'], 'svw_meta_box_nonce')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $video_url = isset($_POST['svw_meta_video_url']) ? esc_url_raw(trim($_POST['svw_meta_video_url'])) : '';
    update_post_meta($post_id, '_svw_video_url', $video_url);

    $video_poster = isset($_POST['svw_meta_video_poster']) ? esc_url_raw(trim($_POST['svw_meta_video_poster'])) : '';
    update_post_meta($post_id, '_svw_video_poster', $video_poster);

    $disable_widget = isset($_POST['svw_meta_disable_widget']) ? '1' : '';
    update_post_meta($post_id, '_svw_disable_widget', $disable_widget);
}
add_action('save_post', 'svw_save_meta_box');

// Вывод виджета во фронте
function svw_render_frontend_widget() {
    // Проверяем, включён ли виджет глобально
    if (!get_option('svw_widget_enabled', '1')) {
        return;
    }

    // Проверяем правила отображения (include / exclude / all)
    if (!svw_should_display_widget()) {
        return;
    }

    // Настройки конкретной страницы из meta box
    $post_id = get_the_ID();
    if ($post_id) {
        // Виджет явно отключён на этой странице
        if (get_post_meta($post_id, '_svw_disable_widget', true) === '1') {
            return;
        }

        // Видео: страничное (meta) > глобальное (option)
        $page_video = get_post_meta($post_id, '_svw_video_url', true);
        $video_url  = esc_url($page_video ?: get_option('svw_video_url'));

        // Постер: страничный (meta) > глобальный (option)
        $page_poster = get_post_meta($post_id, '_svw_video_poster', true);
        $poster_url  = esc_url($page_poster ?: get_option('svw_video_poster', ''));
    } else {
        $video_url  = esc_url(get_option('svw_video_url'));
        $poster_url = esc_url(get_option('svw_video_poster', ''));
    }

    if (!$video_url) {
        return;
    }

    // Получаем настройки
    $button_text    = get_option('svw_button_text', 'Получить КП');
    $button_link    = get_option('svw_button_link', '#section-price');
    $show_on_mobile = get_option('svw_show_on_mobile', '1');
    $autoplay       = get_option('svw_autoplay', '1');

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
                   src="<?php echo $video_url; ?>"
                   <?php if ($poster_url) : ?>poster="<?php echo esc_attr($poster_url); ?>"<?php endif; ?>>
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

    // v1.3.0
    delete_option('svw_video_poster');
    delete_option('svw_appearance_delay');
    delete_option('svw_display_mode');
    delete_option('svw_display_pages');
    delete_option('svw_display_post_types');
    delete_option('svw_button_trigger_selector');
}
register_deactivation_hook(__FILE__, 'svw_deactivate_plugin');
