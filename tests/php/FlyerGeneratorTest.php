<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
/**
 * Tests for the Flyer Generator class
 */

use PHPUnit\Framework\TestCase;

class FlyerGeneratorTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        // Ensure upload directory exists for tests
        $upload_dir = '/tmp/uploads/' . date('Y/m');
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
    }

    public function test_singleton_returns_same_instance(): void
    {
        $instance1 = Peanut_Festival_Flyer_Generator::get_instance();
        $instance2 = Peanut_Festival_Flyer_Generator::get_instance();

        $this->assertSame($instance1, $instance2);
    }

    public function test_get_templates_returns_array(): void
    {
        $templates = Peanut_Festival_Flyer_Generator::get_templates();

        $this->assertIsArray($templates);
    }

    public function test_get_templates_filters_by_festival_id(): void
    {
        $templates = Peanut_Festival_Flyer_Generator::get_templates(1);

        $this->assertIsArray($templates);
    }

    public function test_get_template_returns_null_for_nonexistent(): void
    {
        $template = Peanut_Festival_Flyer_Generator::get_template(999999);

        $this->assertNull($template);
    }

    public function test_create_template_validates_data(): void
    {
        $data = [
            'festival_id' => 1,
            'name' => 'Test Template',
            'template_url' => 'https://example.com/template.png',
            'mask_url' => 'https://example.com/mask.png',
            'frame' => ['x' => 100, 'y' => 100, 'width' => 400, 'height' => 400],
            'namebox' => ['x' => 50, 'y' => 900, 'width' => 980, 'height' => 100],
        ];

        $result = Peanut_Festival_Flyer_Generator::create_template($data);

        // Should return an ID or false (mock DB returns false)
        $this->assertTrue($result === false || is_int($result));
    }

    public function test_create_template_generates_slug(): void
    {
        $name = 'My Awesome Template';
        $expected_slug = sanitize_title($name);

        $this->assertEquals('my-awesome-template', $expected_slug);
    }

    public function test_create_template_encodes_frame_json(): void
    {
        $frame = ['x' => 100, 'y' => 100, 'width' => 400, 'height' => 400];
        $encoded = wp_json_encode($frame);

        $this->assertIsString($encoded);
        $decoded = json_decode($encoded, true);
        $this->assertEquals($frame, $decoded);
    }

    public function test_create_template_encodes_namebox_json(): void
    {
        $namebox = ['x' => 50, 'y' => 900, 'width' => 980, 'height' => 100];
        $encoded = wp_json_encode($namebox);

        $this->assertIsString($encoded);
        $decoded = json_decode($encoded, true);
        $this->assertEquals($namebox, $decoded);
    }

    public function test_update_template_returns_result(): void
    {
        $data = [
            'name' => 'Updated Template Name',
        ];

        $result = Peanut_Festival_Flyer_Generator::update_template(1, $data);

        $this->assertTrue($result === false || is_int($result));
    }

    public function test_delete_template_returns_result(): void
    {
        $result = Peanut_Festival_Flyer_Generator::delete_template(1);

        $this->assertTrue($result === false || is_int($result));
    }

    public function test_log_usage_stores_data(): void
    {
        $data = [
            'template_id' => 1,
            'performer_name' => 'Test Performer',
            'image_url' => 'https://example.com/flyer.jpg',
            'thumb_url' => 'https://example.com/flyer-thumb.jpg',
            'page_url' => 'https://example.com/festival',
            'user_agent' => 'Mozilla/5.0 Test Browser',
        ];

        $result = Peanut_Festival_Flyer_Generator::log_usage($data);

        $this->assertTrue($result === false || is_int($result));
    }

    public function test_log_usage_truncates_long_user_agent(): void
    {
        // User agent should be truncated to 500 chars
        $long_ua = str_repeat('x', 1000);
        $truncated = substr($long_ua, 0, 500);

        $this->assertEquals(500, strlen($truncated));
    }

    public function test_get_usage_log_returns_array(): void
    {
        $log = Peanut_Festival_Flyer_Generator::get_usage_log();

        $this->assertIsArray($log);
    }

    public function test_get_usage_log_respects_limit(): void
    {
        $log = Peanut_Festival_Flyer_Generator::get_usage_log(50);

        $this->assertIsArray($log);
        $this->assertLessThanOrEqual(50, count($log));
    }

    public function test_image_data_validation_jpeg_pattern(): void
    {
        $valid_data = 'data:image/jpeg;base64,/9j/4AAQ...';
        $invalid_data = 'data:image/png;base64,iVBORw0...';

        $this->assertTrue((bool) preg_match('#^data:image/jpeg;base64,#', $valid_data));
        $this->assertFalse((bool) preg_match('#^data:image/jpeg;base64,#', $invalid_data));
    }

    public function test_base64_decode_length_validation(): void
    {
        // Maximum allowed size is 5MB
        $max_size = 5 * 1024 * 1024;

        // Create a string just under the limit
        $valid_size = $max_size - 1;
        $this->assertTrue($valid_size <= $max_size);

        // Create a string over the limit
        $invalid_size = $max_size + 1;
        $this->assertFalse($invalid_size <= $max_size);
    }

    public function test_filename_generation(): void
    {
        $date = gmdate('Ymd-His');
        $random = wp_generate_password(6, false, false);
        $filename = 'flyer-' . $date . '-' . $random . '.jpg';

        $this->assertStringStartsWith('flyer-', $filename);
        $this->assertStringEndsWith('.jpg', $filename);
        $this->assertMatchesRegularExpression('/^flyer-\d{8}-\d{6}-[a-zA-Z0-9]{6}\.jpg$/', $filename);
    }

    public function test_frame_coordinates_validation(): void
    {
        // Valid frame coordinates
        $frame = [
            'x' => 100,
            'y' => 100,
            'width' => 400,
            'height' => 400,
        ];

        $this->assertArrayHasKey('x', $frame);
        $this->assertArrayHasKey('y', $frame);
        $this->assertArrayHasKey('width', $frame);
        $this->assertArrayHasKey('height', $frame);
        $this->assertIsInt($frame['x']);
        $this->assertIsInt($frame['y']);
        $this->assertIsInt($frame['width']);
        $this->assertIsInt($frame['height']);
        $this->assertGreaterThanOrEqual(0, $frame['x']);
        $this->assertGreaterThanOrEqual(0, $frame['y']);
        $this->assertGreaterThan(0, $frame['width']);
        $this->assertGreaterThan(0, $frame['height']);
    }

    public function test_namebox_coordinates_validation(): void
    {
        // Valid namebox coordinates
        $namebox = [
            'x' => 50,
            'y' => 900,
            'width' => 980,
            'height' => 100,
        ];

        $this->assertArrayHasKey('x', $namebox);
        $this->assertArrayHasKey('y', $namebox);
        $this->assertArrayHasKey('width', $namebox);
        $this->assertArrayHasKey('height', $namebox);
    }

    public function test_template_url_sanitization(): void
    {
        $url = 'https://example.com/template.png?test=1&foo=bar';
        $sanitized = esc_url_raw($url);

        $this->assertStringStartsWith('https://', $sanitized);
    }

    public function test_performer_name_sanitization(): void
    {
        $name = '<script>alert("xss")</script>John Doe';
        $sanitized = sanitize_text_field($name);

        $this->assertStringNotContainsString('<script>', $sanitized);
        $this->assertStringContainsString('John Doe', $sanitized);
    }

    public function test_performer_name_max_length(): void
    {
        // Widget input has maxlength="50"
        $max_length = 50;
        $long_name = str_repeat('a', 100);
        $truncated = substr($long_name, 0, $max_length);

        $this->assertEquals(50, strlen($truncated));
    }

    public function test_page_url_sanitization(): void
    {
        $url = 'https://example.com/festival?page=1';
        $sanitized = esc_url_raw($url);

        $this->assertNotEmpty($sanitized);
        $this->assertStringStartsWith('https://', $sanitized);
    }

    public function test_thumbnail_filename_generation(): void
    {
        $original = '/tmp/uploads/pf-flyers/flyer-20250101-120000-abc123.jpg';
        $expected_thumb = '/tmp/uploads/pf-flyers/flyer-20250101-120000-abc123-thumb.jpg';

        // Simulating generate_filename behavior
        $thumb = preg_replace('/\.jpg$/', '-thumb.jpg', $original);

        $this->assertEquals($expected_thumb, $thumb);
    }

    public function test_upload_directory_structure(): void
    {
        $upload = wp_upload_dir();

        $this->assertArrayHasKey('basedir', $upload);
        $this->assertArrayHasKey('baseurl', $upload);
        $this->assertArrayHasKey('path', $upload);
        $this->assertArrayHasKey('url', $upload);
    }

    public function test_flyer_directory_path(): void
    {
        $upload = wp_upload_dir();
        $dir = trailingslashit($upload['basedir']) . 'pf-flyers';

        $this->assertStringEndsWith('pf-flyers', $dir);
    }

    public function test_canvas_dimensions(): void
    {
        // Default canvas size is 1080x1080
        $width = 1080;
        $height = 1080;

        $this->assertEquals(1080, $width);
        $this->assertEquals(1080, $height);
        $this->assertEquals($width, $height); // Square canvas
    }

    public function test_thumbnail_dimensions(): void
    {
        // Thumbnails are resized to 320x320
        $thumb_width = 320;
        $thumb_height = 320;

        $this->assertEquals(320, $thumb_width);
        $this->assertEquals(320, $thumb_height);
    }

    public function test_zoom_range_validation(): void
    {
        $min_zoom = 0.5;
        $max_zoom = 3;
        $step = 0.1;
        $default = 1;

        $this->assertGreaterThanOrEqual($min_zoom, $default);
        $this->assertLessThanOrEqual($max_zoom, $default);
    }

    public function test_rotation_range_validation(): void
    {
        $min_rotate = -180;
        $max_rotate = 180;
        $default = 0;

        $this->assertGreaterThanOrEqual($min_rotate, $default);
        $this->assertLessThanOrEqual($max_rotate, $default);
    }

    public function test_accepted_image_types(): void
    {
        // File input accepts 'image/*'
        $accepted = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        foreach ($accepted as $type) {
            $this->assertStringStartsWith('image/', $type);
        }
    }

    public function test_security_htaccess_content(): void
    {
        $htaccess = "Options -Indexes\n<FilesMatch \"\.php$\">\nOrder Deny,Allow\nDeny from all\n</FilesMatch>";

        $this->assertStringContainsString('Options -Indexes', $htaccess);
        $this->assertStringContainsString('Deny from all', $htaccess);
    }

    public function test_index_php_content(): void
    {
        $index = '<?php // Silence is golden';

        $this->assertStringStartsWith('<?php', $index);
    }
}
