<?php

declare(strict_types=1);

namespace Oxford\CourseDiscovery\Infrastructure;

/**
 * Plugin bootstrap: owns the service registry and all hook bindings.
 *
 * Uses a lightweight manual DI approach (no external container) so the plugin
 * remains dependency-free in production. Every service is lazily instantiated
 * and stored as a singleton within this class.
 *
 * Third-party code may extend behaviour exclusively through WordPress hooks:
 *   - oxford_course_discovery_register_filters   (add Filter Pipeline entries)
 *   - oxford_course_discovery_services           (swap service implementations)
 *   - oxford_course_discovery_rest_args          (modify REST endpoint args)
 */
final class Plugin
{
    private static ?self $instance = null;

    /** @var array<string, object> */
    private array $services = [];

    private bool $booted = false;

    // Singleton – only Plugin::getInstance() may construct this class.
    private function __construct() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Register all WordPress hooks and boot every service provider.
     * Called exactly once from the plugin entry point.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        // Activation / deactivation hooks must be registered before `init`.
        register_activation_hook(COURSE_DISCOVERY_FILE,   [$this, 'onActivation']);
        register_deactivation_hook(COURSE_DISCOVERY_FILE, [$this, 'onDeactivation']);

        add_action('init',          [$this, 'registerPostTypes'],   5);
        add_action('init',          [$this, 'registerTaxonomies'],  5);
        
        // Native Meta Boxes
        $this->getNativeMetaBoxRegistrar()->register();

        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_action('init',          [$this, 'registerShortcodes'], 10);
        add_action('wp_enqueue_scripts', [$this, 'enqueueAssets']);

        // Sync listener registers its own hooks (acf/save_post + fallbacks).
        $this->getSyncListener()->registerHooks();

        /**
         * Allow third-party plugins to register additional services or swap
         * implementations before the plugin fully boots.
         *
         * @hook oxford_course_discovery_services
         * @param Plugin $plugin The plugin bootstrap instance.
         */
        do_action('oxford_course_discovery_services', $this);
    }

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function onActivation(): void
    {
        $this->getMigration()->up();
        flush_rewrite_rules();
    }

    public function onDeactivation(): void
    {
        flush_rewrite_rules();
    }

    // ── Hook Handlers (delegated to services) ─────────────────────────────────

    public function registerPostTypes(): void
    {
        $this->getPostTypeRegistrar()->register();
    }

    public function registerTaxonomies(): void
    {
        $this->getPostTypeRegistrar()->registerTaxonomies();
    }

    public function registerRestRoutes(): void
    {
        $this->getRestController()->registerRoutes();
    }

    public function registerShortcodes(): void
    {
        $this->getShortcode()->register();
    }

    /**
     * Assets are enqueued conditionally by CourseFinderShortcode when the
     * [course_finder] shortcode is rendered. Nothing to do here globally.
     */
    public function enqueueAssets(): void {}


    // ── Service Locator (package-private) ─────────────────────────────────────

    /**
     * Retrieve a named service, constructing it lazily on first access.
     *
     * @template T of object
     * @param class-string<T> $abstract
     * @return T
     */
    public function make(string $abstract): object
    {
        if (! isset($this->services[$abstract])) {
            throw new \RuntimeException(
                "Service [{$abstract}] has not been registered in the Plugin container."
            );
        }

        /** @var T */
        return $this->services[$abstract];
    }

    /**
     * Bind a concrete instance against an abstract class or interface.
     * Intended for use by third-party code via the oxford_course_discovery_services hook.
     *
     * @param class-string $abstract
     * @param object       $concrete
     */
    public function bind(string $abstract, object $concrete): void
    {
        $this->services[$abstract] = $concrete;
    }

    // ── Lazy Service Factories ─────────────────────────────────────────────────

    private function getMigration(): \Oxford\CourseDiscovery\Infrastructure\Database\Migration
    {
        if (! isset($this->services['migration'])) {
            $this->services['migration'] = new \Oxford\CourseDiscovery\Infrastructure\Database\Migration();
        }

        return $this->services['migration']; // @phpstan-ignore-line
    }

    private function getNativeMetaBoxRegistrar(): \Oxford\CourseDiscovery\Infrastructure\WordPress\NativeMetaBoxRegistrar
    {
        if (! isset($this->services['native_meta_box_registrar'])) {
            $this->services['native_meta_box_registrar'] = new \Oxford\CourseDiscovery\Infrastructure\WordPress\NativeMetaBoxRegistrar();
        }

        return $this->services['native_meta_box_registrar']; // @phpstan-ignore-line
    }

    private function getPostTypeRegistrar(): \Oxford\CourseDiscovery\Infrastructure\PostType\PostTypeRegistrar
    {
        if (! isset($this->services['post_type_registrar'])) {
            $this->services['post_type_registrar'] = new \Oxford\CourseDiscovery\Infrastructure\PostType\PostTypeRegistrar();
        }

        return $this->services['post_type_registrar']; // @phpstan-ignore-line
    }

    private function getFilterPipeline(): \Oxford\CourseDiscovery\Infrastructure\Filter\FilterPipeline
    {
        if (! isset($this->services['filter_pipeline'])) {
            $this->services['filter_pipeline'] = new \Oxford\CourseDiscovery\Infrastructure\Filter\FilterPipeline();
        }

        return $this->services['filter_pipeline']; // @phpstan-ignore-line
    }

    private function getRepository(): \Oxford\CourseDiscovery\Domain\Repository\CourseRepositoryInterface
    {
        if (! isset($this->services['repository'])) {
            $this->services['repository'] = new \Oxford\CourseDiscovery\Infrastructure\Repository\WpCourseRepository(
                $this->getFilterPipeline()
            );
        }

        return $this->services['repository']; // @phpstan-ignore-line
    }

    private function getSearchService(): \Oxford\CourseDiscovery\Application\Service\CourseSearchService
    {
        if (! isset($this->services['search_service'])) {
            $this->services['search_service'] = new \Oxford\CourseDiscovery\Application\Service\CourseSearchService(
                $this->getRepository()
            );
        }

        return $this->services['search_service']; // @phpstan-ignore-line
    }

    private function getRestController(): \Oxford\CourseDiscovery\Presentation\RestApi\CourseSearchController
    {
        if (! isset($this->services['rest_controller'])) {
            $this->services['rest_controller'] = new \Oxford\CourseDiscovery\Presentation\RestApi\CourseSearchController(
                $this->getSearchService()
            );
        }

        return $this->services['rest_controller']; // @phpstan-ignore-line
    }

    private function getShortcode(): \Oxford\CourseDiscovery\Presentation\Shortcode\CourseFinderShortcode
    {
        if (! isset($this->services['shortcode'])) {
            $this->services['shortcode'] = new \Oxford\CourseDiscovery\Presentation\Shortcode\CourseFinderShortcode();
        }

        return $this->services['shortcode']; // @phpstan-ignore-line
    }

    private function getSyncListener(): \Oxford\CourseDiscovery\Infrastructure\Sync\CourseSyncListener
    {
        if (! isset($this->services['sync_listener'])) {
            $this->services['sync_listener'] = new \Oxford\CourseDiscovery\Infrastructure\Sync\CourseSyncListener();
        }

        return $this->services['sync_listener']; // @phpstan-ignore-line
    }
}
