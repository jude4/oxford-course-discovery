<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Infrastructure\WordPress;

use WP_Post;

/**
 * Registers native WordPress meta boxes, replacing Advanced Custom Fields (ACF).
 */
final class NativeMetaBoxRegistrar
{
    private const NONCE_ACTION = 'save_oxford_meta';
    private const NONCE_NAME   = 'oxford_meta_nonce';

    public function register(): void
    {
        add_action('add_meta_boxes', [$this, 'addMetaBoxes']);
        add_action('save_post', [$this, 'saveMetaBoxes'], 10, 2);
    }

    public function addMetaBoxes(): void
    {
        add_meta_box(
            'oxford_course_meta',
            'Course Details',
            [$this, 'renderCourseMetaBox'],
            'course',
            'normal',
            'high'
        );

        add_meta_box(
            'oxford_provider_meta',
            'Provider Details',
            [$this, 'renderProviderMetaBox'],
            'provider',
            'normal',
            'high'
        );
    }

    public function renderCourseMetaBox(WP_Post $post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);

        $shortDesc   = get_post_meta($post->ID, 'course_short_description', true);
        $price       = get_post_meta($post->ID, 'course_price', true);
        $instructors = get_post_meta($post->ID, 'course_instructors', true) ?: [];
        $providers   = get_post_meta($post->ID, 'course_providers', true) ?: [];
        
        // Start dates are saved as an array of YYYY-MM strings
        $startDates  = get_post_meta($post->ID, 'course_start_dates', true);
        if (is_string($startDates)) {
            // Migrate old string format to array format on the fly if needed
            $startDates = array_filter(array_map('trim', explode("\n", $startDates)));
        } elseif (!is_array($startDates)) {
            $startDates = [];
        }

        // Get all instructors for select
        $allInstructors = get_posts(['post_type' => 'instructor', 'posts_per_page' => -1]);
        $allProviders   = get_posts(['post_type' => 'provider', 'posts_per_page' => -1]);

        ?>
        <style>
            .oxford-meta-wrapper { margin-top: 10px; }
            .oxford-meta-row { margin-bottom: 20px; }
            .oxford-meta-row label { display: block; font-weight: 600; margin-bottom: 6px; color: #1d2327; }
            .oxford-meta-row input[type="text"], 
            .oxford-meta-row input[type="number"], 
            .oxford-meta-row input[type="month"], 
            .oxford-meta-row select, 
            .oxford-meta-row textarea { 
                width: 100%; 
                max-width: 100%; 
                padding: 4px 8px; 
                border-radius: 4px; 
                border: 1px solid #8c8f94; 
                background-color: #fff; 
                color: #2c3338;
                box-shadow: 0 0 0 transparent; 
                transition: box-shadow 0.1s linear;
            }
            .oxford-meta-row input:focus, 
            .oxford-meta-row select:focus, 
            .oxford-meta-row textarea:focus {
                border-color: #2271b1;
                box-shadow: 0 0 0 1px #2271b1;
                outline: 2px solid transparent;
            }
            .oxford-meta-row .help { color: #646970; font-size: 13px; font-style: italic; display: block; margin-top: 4px; }
            .oxford-date-row input[type="month"] { max-width: 200px; }
        </style>
        <div class="oxford-meta-wrapper">
        <div class="oxford-meta-row">
            <label for="course_short_description">Short Description</label>
            <textarea name="course_short_description" id="course_short_description" rows="3"><?php echo esc_textarea((string)$shortDesc); ?></textarea>
            <span class="help">A brief summary of the course.</span>
        </div>

        <div class="oxford-meta-row">
            <label for="course_price">Price (£)</label>
            <input type="number" name="course_price" id="course_price" value="<?php echo esc_attr((string)$price); ?>" step="0.01" min="0">
        </div>

        <div class="oxford-meta-row">
            <label for="course_instructors">Instructors</label>
            <select name="course_instructors[]" id="course_instructors" multiple size="5">
                <?php foreach ($allInstructors as $inst): ?>
                    <option value="<?php echo esc_attr((string)$inst->ID); ?>" <?php selected(in_array((string)$inst->ID, (array)$instructors)); ?>>
                        <?php echo esc_html($inst->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="help">Hold Ctrl/Cmd to select multiple.</span>
        </div>

        <div class="oxford-meta-row">
            <label for="course_providers">Providers</label>
            <select name="course_providers[]" id="course_providers" multiple size="5">
                <?php foreach ($allProviders as $prov): ?>
                    <option value="<?php echo esc_attr((string)$prov->ID); ?>" <?php selected(in_array((string)$prov->ID, (array)$providers)); ?>>
                        <?php echo esc_html($prov->post_title); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="help">Hold Ctrl/Cmd to select multiple.</span>
        </div>

        <div class="oxford-meta-row">
            <label>Start Dates (Month/Year)</label>
            <span class="help" style="margin-bottom:8px">Select multiple intake months for this course.</span>
            <div id="oxford-start-dates-wrapper">
                <?php 
                $hasDates = false;
                if (!empty($startDates)) {
                    foreach ($startDates as $val) {
                        if ($val === '') continue;
                        $hasDates = true;
                        
                        if (str_contains($val, '-')) {
                            $parts = explode('-', $val);
                            if (!is_numeric($parts[0])) {
                                try {
                                    $sm = \Oxford\CourseDiscovery\Domain\ValueObject\StartMonth::fromString($val);
                                    $val = $sm->toStorageFormat(); // YYYY-MM
                                } catch (\Exception) {
                                    $val = '';
                                }
                            }
                        }
                        ?>
                        <div class="oxford-date-row" style="margin-bottom:5px; display:flex; align-items:center; gap:10px;">
                            <input type="month" name="course_start_dates[]" value="<?php echo esc_attr($val); ?>" min="<?php echo date('Y-m'); ?>">
                            <button type="button" class="button remove-date-btn">Remove</button>
                        </div>
                        <?php
                    }
                }

                // If no dates exist, show at least one empty row
                if (!$hasDates) {
                    ?>
                    <div class="oxford-date-row" style="margin-bottom:5px; display:flex; align-items:center; gap:10px;">
                        <input type="month" name="course_start_dates[]" value="" min="<?php echo date('Y-m'); ?>">
                        <button type="button" class="button remove-date-btn">Remove</button>
                    </div>
                    <?php
                }
                ?>
            </div>
            
            <button type="button" class="button button-primary" id="add-start-date-btn" style="margin-top:10px;">+ Add another date</button>

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const wrapper = document.getElementById('oxford-start-dates-wrapper');
                    const addBtn = document.getElementById('add-start-date-btn');

                    if (!wrapper || !addBtn) return;

                    addBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        const rows = wrapper.querySelectorAll('.oxford-date-row');
                        if (rows.length === 0) return;
                        
                        const newRow = rows[0].cloneNode(true);
                        const input = newRow.querySelector('input');
                        if (input) {
                            input.value = ''; // clear value
                        }
                        
                        wrapper.appendChild(newRow);
                    });

                    // Event delegation for remove buttons
                    wrapper.addEventListener('click', function(e) {
                        if (e.target && e.target.classList.contains('remove-date-btn')) {
                            e.preventDefault();
                            const rows = wrapper.querySelectorAll('.oxford-date-row');
                            if (rows.length > 1) {
                                e.target.closest('.oxford-date-row').remove();
                            } else {
                                // If it's the last one, just clear it
                                const input = e.target.closest('.oxford-date-row').querySelector('input');
                                if (input) input.value = '';
                            }
                        }
                    });
                });
            </script>
        </div>
        </div>
        <?php
    }

    public function renderProviderMetaBox(WP_Post $post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME);
        $location = get_post_meta($post->ID, 'provider_location', true);
        ?>
        <style>
            .oxford-meta-wrapper { margin-top: 10px; }
            .oxford-meta-row { margin-bottom: 20px; }
            .oxford-meta-row label { display: block; font-weight: 600; margin-bottom: 6px; color: #1d2327; }
            .oxford-meta-row input[type="text"] { 
                width: 100%; 
                max-width: 400px; 
                padding: 4px 8px; 
                border-radius: 4px; 
                border: 1px solid #8c8f94; 
                background-color: #fff; 
                color: #2c3338;
                box-shadow: 0 0 0 transparent; 
                transition: box-shadow 0.1s linear;
            }
            .oxford-meta-row input:focus {
                border-color: #2271b1;
                box-shadow: 0 0 0 1px #2271b1;
                outline: 2px solid transparent;
            }
        </style>
        <div class="oxford-meta-wrapper">
            <div class="oxford-meta-row">
                <label for="provider_location">Location</label>
                <input type="text" name="provider_location" id="provider_location" value="<?php echo esc_attr((string)$location); ?>">
            </div>
        </div>
        <?php
    }

    public function saveMetaBoxes(int $postId, WP_Post $post): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!isset($_POST[self::NONCE_NAME]) || !wp_verify_nonce($_POST[self::NONCE_NAME], self::NONCE_ACTION)) {
            return;
        }

        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        if ($post->post_type === 'course') {
            $this->saveMeta($postId, 'course_short_description', sanitize_textarea_field($_POST['course_short_description'] ?? ''));
            $this->saveMeta($postId, 'course_price', sanitize_text_field($_POST['course_price'] ?? ''));
            
            $instructors = array_map('intval', $_POST['course_instructors'] ?? []);
            $this->saveMeta($postId, 'course_instructors', $instructors);
            
            $providers = array_map('intval', $_POST['course_providers'] ?? []);
            $this->saveMeta($postId, 'course_providers', $providers);

            // Handle start dates array
            $startDates = $_POST['course_start_dates'] ?? [];
            $validDates = [];
            foreach ((array)$startDates as $date) {
                $date = sanitize_text_field($date);
                if ($date !== '') {
                    $validDates[] = $date; // YYYY-MM
                }
            }
            $this->saveMeta($postId, 'course_start_dates', $validDates);
        }

        if ($post->post_type === 'provider') {
            $this->saveMeta($postId, 'provider_location', sanitize_text_field($_POST['provider_location'] ?? ''));
        }
    }

    private function saveMeta(int $postId, string $key, mixed $value): void
    {
        if (empty($value)) {
            delete_post_meta($postId, $key);
        } else {
            update_post_meta($postId, $key, $value);
        }
    }
}
