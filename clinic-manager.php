<?php

/*
 * Plugin Name:       Clinic manager - clinic portal
 * Description:       A custom plugin made for allowing clinic owners to add their clinics
 * Version:           1.8.5
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Kazmi Webwhiz
 * Author URI:        https://kazmiwebwhiz.com/
 * Text Domain:       clinic-manager
 */
// Enqueue plugin styles and scripts

function clinic_manager_enqueue_assets() {
    wp_enqueue_script('jquery-ui-sortable');
    wp_enqueue_style('clinic-manager-styles', plugin_dir_url(__FILE__) . 'assets/clinicstyle.css');
    wp_enqueue_script('clinic-manager-scripts', plugin_dir_url(__FILE__) . 'assets/clinicscript.js', array('jquery'), null, true);
    wp_localize_script('clinic-manager-scripts', 'custom_post_form_vars', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('custom_post_form_nonce'),
    ));
}
add_action('wp_enqueue_scripts', 'clinic_manager_enqueue_assets');

function custom_post_form_shortcode() {
    $current_user = wp_get_current_user();
    $current_languages = pll_current_language();

    $args = array(
        'post_type' => 'clinic',
        'author' => $current_user->ID,
        'post_status' => array('publish', 'draft', 'pending'),
        'posts_per_page' => 1,
        'lang' => $current_languages,
    );

    $existing_posts = get_posts($args);
    $existing_post = !empty($existing_posts) ? $existing_posts[0] : null;
    $destination = get_terms(array(
        'taxonomy' => 'destination',
        'hide_empty' => false,
    ));

    $specialties = get_terms(array(
        'taxonomy' => 'specialty',
        'hide_empty' => false,
    ));


    $current_user_id = get_current_user_id();
    $post_en_id = get_user_meta($current_user_id, '_lang_en_post_id', true);
    $post_ar_id = get_user_meta($current_user_id, '_lang_ar_post_id', true);
    $post_title = $existing_post ? $existing_post->post_title : '';
    $profile_overview = $existing_post ? get_field('profile_overview', $existing_post->ID) : '';
    $clinics_awards = $existing_post ? get_field('clinics_awards', $existing_post->ID) : '';
    $full_address = $existing_post ? get_field('full_address', $existing_post->ID) : '';
    $maps = $existing_post ? get_field('maps', $existing_post->ID) : '';
    $post_destination = $existing_post ? wp_get_post_terms($existing_post->ID, 'destination', array('fields' => 'ids')) : array();
    $post_specialties = $existing_post ? wp_get_post_terms($existing_post->ID, 'specialty', array('fields' => 'ids')) : array();
    $procedures = $existing_post ? get_field('procedures', $existing_post->ID) : array();
    $doctors = $existing_post ? get_field('doctors', $existing_post->ID) : array();
    $featured_image_url = $existing_post ? wp_get_attachment_url(get_post_thumbnail_id($existing_post->ID)) : '';

    // Fetch existing images for display
    $clinic_images_urls = [];
    if ($existing_post) {
        // Get all gallery images from the custom field 'clinicimagegallery'
        $gallery_image_ids = get_post_meta($existing_post->ID, 'clinicimagegallery', true);
        if (!empty($gallery_image_ids) && is_array($gallery_image_ids)) {
            foreach ($gallery_image_ids as $image_id) {
                // Check if each ID is a valid attachment and get the URL
                $image_url = wp_get_attachment_url($image_id);
                if ($image_url) {
                    $clinic_images_urls[$image_id] = $image_url;
                }
            }
        }
    }


    ob_start();
    ?>
    <form id="custom-post-form" method="post" enctype="multipart/form-data">
        <input type="hidden" name="post_id" value="<?php echo esc_attr($existing_post ? $existing_post->ID : ''); ?>">
        <input type="hidden" name="lang" value="<?php echo $current_languages; ?>">
        <input type="hidden" name="lang_en" value="<?php echo esc_attr($post_en_id); ?>">
        <input type="hidden" name="lang_ar" value="<?php echo esc_attr($post_ar_id); ?>">

        <?php if ($existing_post) : ?>
    <div class="clinic-status" style="display: flex; align-items: center;">
        <?php if ($existing_post->post_status === 'publish') : ?>
            <span style="color:green;">
            <?php esc_html_e('Status: Published', 'clinic-manager'); ?>
                <a href="<?php echo esc_url(get_permalink($existing_post->ID)); ?>" target="_blank"><?php esc_html_e('View Clinic', 'clinic-manager'); ?></a>
            </span>
        <?php elseif ($existing_post->post_status === 'pending') : ?>
            <span style="color:red;">
            <?php esc_html_e('Status: Pending', 'clinic-manager'); ?>
            </span>
        <?php endif; ?>

        <?php
                $current_language = pll_current_language();
                $other_language = ($current_language === 'en') ? 'ar' : 'en';
                $other_lang_post_id = ($current_language === 'en') ? $post_ar_id : $post_en_id;
                if (!$other_lang_post_id || get_post_status($other_lang_post_id) === false || get_post_status($other_lang_post_id) === 'trash') : ?>
                    <div class="clinic-notice">
                        <span style="color:red;">
                        , <?php esc_html_e('Switch the language to create a Clinic in', 'clinic-manager'); ?> <?php echo ($current_language === 'en') ? esc_html_e('Arabic', 'clinic-manager') : esc_html_e('English', 'clinic-manager') ?>.
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>


        <div class="form-group">
            <label for="post_title"><?php esc_html_e('Title', 'clinic-manager'); ?>:</label>
            <input type="text" id="post_title" name="post_title" value="<?php echo esc_attr($post_title); ?>" required>
        </div>

        <!-- HTML -->
        <div class="form-group">
            <label for="clinic_images">
                <?php esc_html_e('Clinic Images', 'clinic-manager'); ?>
                <span style="font-weight: 400">
                    (<?php esc_html_e('Recommended size', 'clinic-manager'); ?>: 320px x 240px)
                </span>:
            </label>

            <div class="clinic-images-container">
                <?php if (!empty($clinic_images_urls)) : ?>
                    <?php foreach ($clinic_images_urls as $image_id => $image_url) : ?>
                        <div class="clinic-image-wrapper" data-image-id="<?php echo esc_attr($image_id); ?>">
                            <img src="<?php echo esc_url($image_url); ?>" alt="<?php esc_html_e('Clinic Image', 'clinic-manager'); ?>" class="clinic-image">
                            <button type="button" class="remove-image-button" data-image-id="<?php echo esc_attr($image_id); ?>">×</button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <label class="upload-button" for="clinic_images">Upload Images</label>
            <input type="file" id="clinic_images" name="clinic_images[]" accept="image/*" multiple>
            <input type="hidden" name="clinic_images_order" id="clinic_images_order" value="">
        </div>



        <div class="form-group">
            <label for="profile_overview"><?php esc_html_e('Profile Overview', 'clinic-manager'); ?>
            :</label>
            <?php wp_editor($profile_overview, 'profile_overview', array('textarea_name' => 'profile_overview')); ?>
        </div>

        <div class="form-group">
            <label for="clinics_awards"><?php esc_html_e('Clinics & Awards', 'clinic-manager'); ?>
            :</label>
            <?php wp_editor($clinics_awards, 'clinics_awards', array('textarea_name' => 'clinics_awards')); ?>
        </div>

        <div class="form-group">
            <label for="full_address"><?php esc_html_e('Full Address', 'clinic-manager'); ?>
            :</label>
            <input type="text" id="full_address" name="full_address" value="<?php echo esc_attr($full_address); ?>" required>
        </div>

        <div class="form-group">
            <label for="maps"><?php esc_html_e('Location', 'clinic-manager'); ?>
            :</label>
            <input type="text" id="maps" name="maps" value="<?php echo esc_attr($maps); ?>" required readonly>
            <div id="map" style="height: 400px; width: 100%;"></div>
            <input type="hidden" id="maps_coordinates" name="maps_coordinates" value="<?php echo esc_attr($maps); ?>">
        </div>

        <div class="form-group">
            <label for="destination"><?php esc_html_e('Destination', 'clinic-manager'); ?>
            :</label>
            <select id="destination" name="destination" required>
                <option value=""><?php esc_html_e('Select a destination', 'clinic-manager'); ?>
                </option>
                <?php
                if (!empty($destination) && !is_wp_error($destination)) {
                    foreach ($destination as $dest) {
                        $selected = in_array($dest->term_id, $post_destination) ? 'selected' : '';
                        echo '<option value="' . esc_attr($dest->term_id) . '" ' . $selected . '>' . esc_html($dest->name) . '</option>';
                    }
                }
                ?>
            </select>
        </div>

        <div class="form-group">
            <label for="specialty"><?php esc_html_e('Specialty', 'clinic-manager'); ?>
            :</label>
            <div id="specialties-wrapper" style="display: flex; flex-wrap: wrap;">
                <?php
                if (!empty($specialties) && !is_wp_error($specialties)) {
                    foreach ($specialties as $specialty) {
                        $checked = in_array($specialty->term_id, $post_specialties) ? 'checked' : '';
                        echo '<label class="specialty-label" style="display: flex; align-items: center; margin-right: 10px; white-space: nowrap;"><input type="checkbox" name="specialty[]" value="' . esc_attr($specialty->term_id) . '" ' . $checked . '> <span style="white-space: nowrap;">' . esc_html($specialty->name) . '</span></label>';
                    }
                }
                ?>
            </div>
        </div>

        <div class="form-group">
            <label for="procedures"><?php esc_html_e('Procedures', 'clinic-manager'); ?>
            :</label>
            <div id="procedures-wrapper">
                <?php if (!empty($procedures)) : ?>
                    <?php foreach ($procedures as $procedure) : ?>
                        <div class="procedure">
                            <input type="text" name="procedure_name[]" value="<?php echo esc_attr($procedure['name']); ?>" placeholder="<?php esc_html_e('Procedure Name', 'clinic-manager'); ?>"
>
                            <textarea name="procedure_description[]" placeholder="<?php esc_html_e('Procedure Description', 'clinic-manager'); ?>"
><?php echo esc_html($procedure['description']); ?></textarea>
                            <input type="number" name="procedure_price[]" value="<?php echo esc_attr($procedure['price']); ?>" placeholder="<?php esc_html_e('Procedure Price', 'clinic-manager'); ?>"
>
                            <button type="button" class="remove-procedure"><?php esc_html_e('Remove', 'clinic-manager'); ?>
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <div class="procedure">
                        <input type="text" name="procedure_name[]" placeholder="<?php esc_html_e('Procedure Name', 'clinic-manager'); ?>"
>
                        <textarea name="procedure_description[]" placeholder="<?php esc_html_e('Procedure Description', 'clinic-manager'); ?>"
></textarea>
                        <input type="number" name="procedure_price[]" placeholder="<?php esc_html_e('Procedure Price', 'clinic-manager'); ?>"
>
                        <button type="button" class="remove-procedure"><?php esc_html_e('Remove', 'clinic-manager'); ?>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            <button type="button" id="add-procedure"><?php esc_html_e('Add Procedure', 'clinic-manager'); ?>
            </button>
        </div>
        <div class="form-group">
            <label for="doctors"><?php esc_html_e('Doctors', 'clinic-manager'); ?>
            :</label>
            <div id="doctors-wrapper">
                <?php if (!empty($doctors)) : ?>
                    <?php foreach ($doctors as $doctor) : 
                        $doctor_image_id = !empty($doctor['dr_image']) ? $doctor['dr_image'] : '';
                        $doctor_image_url = $doctor_image_id ? wp_get_attachment_url($doctor_image_id) : '';
                    ?>
                        <div class="doctor">
                            <input type="hidden" name="doctor_image_id[]" value="<?php echo esc_attr($doctor_image_id); ?>">
                            <input type="text" name="drname[]" value="<?php echo esc_attr($doctor['drname']); ?>" placeholder="<?php esc_html_e('Doctor Name', 'clinic-manager'); ?>">
                            <input type="text" name="designation[]" value="<?php echo esc_attr($doctor['designation']); ?>" placeholder="<?php esc_html_e('Designation', 'clinic-manager'); ?>">
                            <?php if ($doctor_image_url) : ?>
                                <img src="<?php echo esc_url($doctor_image_url); ?>" alt="Doctor Image" style="max-width: 200px; display: block;">
                            <?php endif; ?>
                            <input type="file" name="dr_image[]" accept="image/*">
                            <input type="text" name="dr_location[]" value="<?php echo esc_attr($doctor['dr_location']); ?>" placeholder="<?php esc_html_e('Doctor Location', 'clinic-manager'); ?>">
                            <input type="text" name="experience[]" value="<?php echo esc_attr($doctor['experience']); ?>" placeholder="<?php esc_html_e('Experience', 'clinic-manager'); ?>">
                            <textarea name="description[]" placeholder="<?php esc_html_e('Description', 'clinic-manager') ?>"><?php echo esc_html($doctor['description']) ?></textarea>
                            <button type="button" class="remove-doctor"><?php esc_html_e('Remove', 'clinic-manager') ?>
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <div class="doctor">
                        <input type="hidden" name="doctor_image_id[]" value=""> 
                        <input type="text" name="drname[]" placeholder="<?php esc_html_e('Doctor Name', 'clinic-manager') ?>">
                        <input type="text" name="designation[]" placeholder="<?php esc_html_e('Designation', 'clinic-manager') ?>">
                        <input type="file" name="dr_image[]" accept="image/*">
                        <input type="text" name="dr_location[]" placeholder="<?php esc_html_e('Doctor Location', 'clinic-manager'); ?>">
                        <input type="text" name="experience[]" placeholder="<?php esc_html_e('Experience', 'clinic-manager') ?>">
                        <textarea name="description[]" placeholder="<?php esc_html_e('Description', 'clinic-manager') ?>"></textarea>
                        <button type="button" class="remove-doctor"><?php esc_html_e('Remove', 'clinic-manager') ?>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            <button type="button" id="add-doctor"><?php esc_html_e('Add Doctor', 'clinic-manager'); ?>
            </button>
        </div>

        <input type="submit" name="submit_post" value="<?php echo $existing_post ? esc_html_e('Update', 'clinic-manager') : esc_html_e('Submit', 'clinic-manager') ?>">
    </form>
<!-- Loader -->
<div id="form-loader" class="form-loader" style="display:none;"></div>


<style>
    
/* Style for the loader */
.form-loader {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(255, 255, 255, 0.7);
    z-index: 9999;
}

.form-loader::after {
    content: "";
    display: block;
    position: absolute;
    top: 50%;
    left: 50%;
    width: 40px;
    height: 40px;
    margin-top: -20px;
    margin-left: -20px;
    border-radius: 50%;
    border: 4px solid #000;
    border-top-color: transparent;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    100% {
        transform: rotate(360deg);
    }
}
</style>

    <script src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY&callback=initMap" async defer></script>
    <script>
        function initMap() {
    var initialLocation = { lat: -34.397, lng: 150.644 };
    var map = new google.maps.Map(document.getElementById('map'), {
        zoom: 8,
        center: initialLocation
    });

    var marker = new google.maps.Marker({
        position: initialLocation,
        map: map,
        draggable: true
    });

    google.maps.event.addListener(marker, 'dragend', function() {
        var position = marker.getPosition();
        document.getElementById('maps').value = position.lat() + ',' + position.lng();
        document.getElementById('maps_coordinates').value = position.lat() + ',' + position.lng();
    });

    // Set marker to existing coordinates if available
    var existingCoordinates = document.getElementById('maps_coordinates').value;
    if (existingCoordinates) {
        var coords = existingCoordinates.split(',');
        var latLng = new google.maps.LatLng(parseFloat(coords[0]), parseFloat(coords[1]));
        marker.setPosition(latLng);
        map.setCenter(latLng);
    }
}
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('custom_post_form', 'custom_post_form_shortcode');


function handle_custom_post_form_submission() {

    check_ajax_referer('custom_post_form_nonce', 'nonce');

    // Get the current user ID
    $user_id = get_current_user_id();

    // Sanitize and process input data
    $current_language = isset($_POST['lang']) ? sanitize_text_field($_POST['lang']) : '';
    $lang_en_id = isset($_POST['lang_en']) ? intval($_POST['lang_en']) : 0;
    $lang_ar_id = isset($_POST['lang_ar']) ? intval($_POST['lang_ar']) : 0;
    $post_id = 0;

    if ($current_language === 'en') {
        $post_id = $lang_en_id;
    } elseif ($current_language === 'ar') {
        $post_id = $lang_ar_id;
    }

    // If no valid post ID is found or the post is invalid, create a new one
    if (!$post_id || !is_valid_post($post_id)) {
        $post_title = sanitize_text_field($_POST['post_title']);

        $new_post = array(
            'post_title'   => $post_title,
            'post_status'  => 'pending',
            'post_type'    => 'clinic',
            'post_author'  => $user_id,
        );

        $post_id = wp_insert_post($new_post);

        if (is_wp_error($post_id)) {
            wp_send_json_error('Error creating new post.');
        }

        if ($current_language === 'en') {
            update_user_meta($user_id, '_lang_en_post_id', $post_id);
        } elseif ($current_language === 'ar') {
            update_user_meta($user_id, '_lang_ar_post_id', $post_id);
        }

        if (function_exists('pll_set_post_language')) {
            pll_set_post_language($post_id, $current_language);
        }

    } else {
        $post_update = array(
            'ID'            => $post_id,
            'post_title'    => sanitize_text_field($_POST['post_title']),
            'post_status'   => 'pending',
        );

        $updated_post_id = wp_update_post($post_update);

        if (is_wp_error($updated_post_id)) {
            wp_send_json_error('Error updating post.');
        }

        // Set the language for the updated post
        if (function_exists('pll_set_post_language')) {
            pll_set_post_language($updated_post_id, $current_language);
        }

        $post_id = $updated_post_id;
    }

    // Update translations to 'pending' status
    if (function_exists('pll_get_post_translations')) {
        $translations = pll_get_post_translations($post_id);

        foreach ($translations as $lang => $translation_id) {
            if ($translation_id) {
                wp_update_post(array(
                    'ID' => $translation_id,
                    'post_status' => 'pending',
                ));
            }
        }
    }

    // Process the rest of the form data
    $destination = isset($_POST['destination']) ? intval($_POST['destination']) : 0;
    $specialties = isset($_POST['specialty']) ? array_map('intval', $_POST['specialty']) : array();
    $full_address = sanitize_text_field($_POST['full_address']);
    $maps_coordinates = sanitize_text_field($_POST['maps_coordinates']);
    $profile_overview = isset($_POST['profile_overview']) ? wp_kses_post($_POST['profile_overview']) : '';
    $clinics_awards = isset($_POST['clinics_awards']) ? wp_kses_post($_POST['clinics_awards']) : '';

    // Update post fields
    update_field('full_address', $full_address, $post_id);
    update_field('maps', $maps_coordinates, $post_id);
    update_field('profile_overview', $profile_overview, $post_id);
    update_field('clinics_awards', $clinics_awards, $post_id);


    // Update procedures
    if (isset($_POST['procedure_name'])) {
        $procedures = array();
        foreach ($_POST['procedure_name'] as $key => $name) {
            $description = sanitize_textarea_field($_POST['procedure_description'][$key]);
            $price = floatval($_POST['procedure_price'][$key]);

            if (!empty($name) || !empty($description) || !empty($price)) {
                $procedures[] = array(
                    'name'        => sanitize_text_field($name),
                    'description' => $description,
                    'price'       => $price,
                );
            }
        }
        update_field('procedures', !empty($procedures) ? $procedures : null, $post_id);
    }

    // Handle Doctors
    if (isset($_POST['drname'])) {
        $doctors = array();
        foreach ($_POST['drname'] as $key => $drname) {
            $designation = sanitize_text_field($_POST['designation'][$key]);
            $location = sanitize_text_field($_POST['dr_location'][$key]);
            $experience = sanitize_text_field($_POST['experience'][$key]);
            $description = sanitize_textarea_field($_POST['description'][$key]);
            $doctor_image_id = isset($_POST['doctor_image_id'][$key]) ? intval($_POST['doctor_image_id'][$key]) : '';

            // Handle doctor image upload
            if (!empty($_FILES['dr_image']['name'][$key])) {
                if ($_FILES['dr_image']['error'][$key] == UPLOAD_ERR_OK) {
                    $file = array(
                        'name'     => $_FILES['dr_image']['name'][$key],
                        'type'     => $_FILES['dr_image']['type'][$key],
                        'tmp_name' => $_FILES['dr_image']['tmp_name'][$key],
                        'error'    => $_FILES['dr_image']['error'][$key],
                        'size'     => $_FILES['dr_image']['size'][$key],
                    );
                    $upload_overrides = array('test_form' => false);
                    $uploaded_image = wp_handle_upload($file, $upload_overrides);
                    if ($uploaded_image && !isset($uploaded_image['error'])) {
                        $doctor_image_id = wp_insert_attachment(array(
                            'post_mime_type' => $uploaded_image['type'],
                            'post_title'     => sanitize_file_name($file['name']),
                            'post_content'   => '',
                            'post_status'    => 'inherit'
                        ), $uploaded_image['file']);
                        require_once(ABSPATH . 'wp-admin/includes/image.php');
                        $attach_data = wp_generate_attachment_metadata($doctor_image_id, $uploaded_image['file']);
                        wp_update_attachment_metadata($doctor_image_id, $attach_data);
                    } else {
                        error_log('Error uploading doctor image: ' . $uploaded_image['error']);
                    }
                } else {
                    error_log('Error in doctor image upload for index ' . $key . '. Error code: ' . $_FILES['dr_image']['error'][$key]);
                }
            }

            if (!empty($drname) || !empty($designation) || !empty($location) || !empty($experience) || !empty($description) || !empty($doctor_image_id)) {
                $doctors[] = array(
                    'drname'       => $drname,
                    'designation'  => $designation,
                    'dr_image'     => $doctor_image_id,
                    'dr_location'  => $location,
                    'experience'   => $experience,
                    'description'  => $description,
                );
            }
        }
        update_field('doctors', !empty($doctors) ? $doctors : null, $post_id);
    }

    // Handle Clinic Images
    if (isset($_POST['clinic_images_order'])) {
        // Decode JSON to array
        $clinic_images_order = json_decode(stripslashes($_POST['clinic_images_order']), true);
        $updated_images = []; // This will store the actual attachment IDs for saving

        // Process new image uploads
        if (!empty($_FILES['clinic_images']['name'][0])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            require_once(ABSPATH . 'wp-admin/includes/image.php');

            $upload_overrides = array('test_form' => false);
            $temp_id_index = 0; // Track the current temporary ID index for replacement

            foreach ($_FILES['clinic_images']['name'] as $key => $value) {
                if ($_FILES['clinic_images']['error'][$key] == UPLOAD_ERR_OK) {
                    $file = array(
                        'name'     => $_FILES['clinic_images']['name'][$key],
                        'type'     => $_FILES['clinic_images']['type'][$key],
                        'tmp_name' => $_FILES['clinic_images']['tmp_name'][$key],
                        'error'    => $_FILES['clinic_images']['error'][$key],
                        'size'     => $_FILES['clinic_images']['size'][$key],
                    );

                    // Handle the file upload
                    $uploaded_image = wp_handle_upload($file, $upload_overrides);
                    if ($uploaded_image && !isset($uploaded_image['error'])) {
                        $attachment = array(
                            'post_mime_type' => $uploaded_image['type'],
                            'post_title'     => sanitize_file_name($file['name']),
                            'post_content'   => '',
                            'post_status'    => 'inherit',
                        );
                        $attach_id = wp_insert_attachment($attachment, $uploaded_image['file'], $post_id);
                        $attach_data = wp_generate_attachment_metadata($attach_id, $uploaded_image['file']);
                        wp_update_attachment_metadata($attach_id, $attach_data);

                        // Replace temporary ID with real attachment ID in image order
                        while ($temp_id_index < count($clinic_images_order) && strpos($clinic_images_order[$temp_id_index], 'temp-') === false) {
                            $temp_id_index++; // Move to the next temporary ID
                        }

                        if ($temp_id_index < count($clinic_images_order) && strpos($clinic_images_order[$temp_id_index], 'temp-') === 0) {
                            $clinic_images_order[$temp_id_index] = $attach_id;
                        }
                    } else {
                        error_log('Error uploading image: ' . $uploaded_image['error']);
                    }
                } else {
                    error_log('Error in file upload for image index ' . $key . '. Error code: ' . $_FILES['clinic_images']['error'][$key]);
                }
            }
        }

        // Verify each ID in clinic_images_order is a valid attachment ID
        foreach ($clinic_images_order as $image_id) {
            if (wp_attachment_is_image($image_id)) {
                $updated_images[] = $image_id; // Add valid attachment ID to the final list
            } else {
                error_log('Invalid image ID detected and skipped: ' . $image_id);
            }
        }


        // Save the updated list of image IDs to the ACF field
        update_field('clinicimagegallery', $updated_images, $post_id);

        // Set the first image in the reordered list as the featured image
        if (!empty($updated_images)) {
            set_post_thumbnail($post_id, $updated_images[0]);
        } else {
            // If no images are left, remove the featured image
            delete_post_thumbnail($post_id);
            update_field('clinicimagegallery', [], $post_id); // Clear the field if no images left
        }
    }

    // Handle Language-Specific Terms
    if ($current_language === 'en' && $lang_ar_id) {
        $destination_translation = pll_get_term($destination, 'ar');
        $specialty_translations = array_map(function($term_id) {
            return pll_get_term($term_id, 'ar');
        }, $specialties);

        wp_set_post_terms($lang_ar_id, [$destination_translation], 'destination');
        wp_set_post_terms($lang_ar_id, $specialty_translations, 'specialty');
    } elseif ($current_language === 'ar' && $lang_en_id) {
        $destination_translation = pll_get_term($destination, 'en');
        $specialty_translations = array_map(function($term_id) {
            return pll_get_term($term_id, 'en');
        }, $specialties);

        wp_set_post_terms($lang_en_id, [$destination_translation], 'destination');
        wp_set_post_terms($lang_en_id, $specialty_translations, 'specialty');
    }

    wp_set_post_terms($post_id, [$destination], 'destination');
    wp_set_post_terms($post_id, $specialties, 'specialty');
   

    // Save translations
    if (function_exists('pll_save_post_translations')) {
        $translations = array();
        if ($current_language === 'en') {
            $translations = array('en' => $post_id, 'ar' => $lang_ar_id);
        } elseif ($current_language === 'ar') {
            $translations = array('ar' => $post_id, 'en' => $lang_en_id);
        }
        pll_save_post_translations($translations);
       
    }

    // Send success response
    wp_send_json_success(array(
        'message' => 'Your post has been saved successfully.',
        'post_id' => $post_id,
    ));
}
add_action('wp_ajax_handle_custom_post_form', 'handle_custom_post_form_submission');
add_action('wp_ajax_nopriv_handle_custom_post_form', 'handle_custom_post_form_submission');

// Helper function to check if the post is valid and not trashed
function is_valid_post($post_id) {
    $post = get_post($post_id);
    return $post && $post->post_status !== 'trash';
}

// Show doctors on single clinic page
function clinic_doctors_shortcode() {
    $clinic_id = get_the_ID();
    $doctors = get_field('doctors', $clinic_id);
    $output = '';
    if ($doctors) {
        $doctors_by_designation = [];
        foreach ($doctors as $doctor) {
            $designation = $doctor['designation'];
            if (!isset($doctors_by_designation[$designation])) {
                $doctors_by_designation[$designation] = [];
            }
            $doctors_by_designation[$designation][] = $doctor;
        }
        $output .= '<div class="clinic-doctors-container">';
        foreach ($doctors_by_designation as $designation => $doctors_list) {
            $output .= '<div class="designation-container">';
            $output .= '<h3 class="designation-title" tabindex="0">' . esc_html($designation) . ' <span class="arrow">▼</span></h3>';
            $output .= '<div class="doctors-list" style="display: none;">';
            foreach ($doctors_list as $doctor) {
                $name = !empty($doctor['drname']) ? $doctor['drname'] : 'N/A';
                $location = !empty($doctor['dr_location']) ? $doctor['dr_location'] : 'N/A';
                $experience = !empty($doctor['experience']) ? $doctor['experience'] : 'N/A';
                $description = !empty($doctor['description']) ? $doctor['description'] : 'N/A';
                $image_id = $doctor['dr_image'];
                $image_url = wp_get_attachment_image_url($image_id, 'full');
                $output .= '<div class="doctor-card">';
                $output .= '<div class="doctor-left-container">';
                if ($image_url) {
                    $output .= '<div class="doctor-image"><img src="' . esc_url($image_url) . '" alt="' . esc_attr($name) . '"></div>';
                } else {
                    $output .= '<div class="doctor-image"><img src="default-image-url.jpg" alt="Default Image"></div>';
                }
                $output .= '</div>';
                $output .= '<div class="doctor-right-container">';
                $output .= '<h3 class="doctor-name"><span class="doctor-info">' . esc_html($name) . '</span></h3>';
                $output .= '<div class="doctor-location"><div class="icon-circle"><i class="fas fa-map-marker-alt"></i></div> <span class="doctor-info">' . esc_html($location) . '</span></div>';
                $output .= '<div class="doctor-experience"><div class="icon-circle"><i class="fas fa-clock"></i></div> <span class="doctor-info">Experience:</span> ' . esc_html($experience) . '</div>';
                $output .= '<div class="doctor-description"><div class="icon-circle"><i class="fas fa-info-circle"></i></div> ' . esc_html($description) . '</div>';
                $output .= '</div>';
                $output .= '</div>';
            }
            $output .= '</div>';
            $output .= '</div>';
        }
        $output .= '</div>';
    } else {
        $output = '<p>There are no doctors to show.</p>';
    }
    return $output;
}
add_shortcode('clinic_doctors', 'clinic_doctors_shortcode');


// Add a new column for the author and adjust its position
function add_clinic_author_column($columns) {
    $new_columns = array();
    
    // Move all existing columns to the new array
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
    }

    // Add the Author column at the end
    $new_columns['clinic_author'] = __('Author', 'textdomain');
    
    return $new_columns;
}
add_filter('manage_clinic_posts_columns', 'add_clinic_author_column');

// Display the author name in the new column
function show_clinic_author_column($column, $post_id) {
    if ($column === 'clinic_author') {
        $author_id = get_post_field('post_author', $post_id);
        $author_name = get_the_author_meta('display_name', $author_id);
        echo esc_html($author_name);
    }
}
add_action('manage_clinic_posts_custom_column', 'show_clinic_author_column', 10, 2);

// Make the author column sortable
function make_clinic_author_column_sortable($columns) {
    $columns['clinic_author'] = 'author';
    return $columns;
}
add_filter('manage_edit-clinic_sortable_columns', 'make_clinic_author_column_sortable');

// Adjust the column width
function clinic_author_column_width() {
    echo '<style type="text/css">
        .column-clinic_author { width: 10%; }
    </style>';
}
add_action('admin_head', 'clinic_author_column_width');


?>