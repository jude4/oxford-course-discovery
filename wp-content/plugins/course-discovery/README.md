# Course Discovery Plugin

A strictly typed, scalable, and highly accessible Course Discovery system for WordPress. Designed using Domain-Driven Design (DDD) principles and custom database indexing to avoid the performance pitfalls of standard `WP_Query` and meta-queries.

## Features

- **Domain-Driven Design:** Core business logic lives in `src/Domain`, entirely decoupled from WordPress internals. Uses Value Objects for precise validation (`StartMonth`, `Price`, `CourseId`).
- **Custom Search Index:** Courses, relationships, and meta-data are synced into a denormalised, flat `wp_course_index` table on save. This allows multi-faceted queries to execute in <5ms without expensive SQL `JOIN`s or meta queries.
- **Strict Typing:** `declare(strict_types=1);` used universally. Full parameter and return type coverage.
- **REST API:** Provides a clean `/wp-json/course-discovery/v1/courses` endpoint that accepts parsed DTOs.
- **WAI-ARIA 1.2 Frontend:** The React-free, vanilla JS frontend uses semantic HTML and the WAI-ARIA 1.2 Combobox pattern for complex multi-select dropdowns. Fully navigable via keyboard with live screen reader announcements.
- **Extensible Pipeline:** The search query generation uses a `FilterPipeline` design pattern. Third-party plugins can inject custom filters (e.g. `AuthorFilter`, `DurationFilter`) via WordPress hooks without altering core code.

## Requirements

- PHP 8.1+
- WordPress 6.0+
- Composer

## Installation

1. Clone this repository into your `wp-content/plugins` directory.
2. Run `composer install --no-dev -o` to generate the PSR-4 autoloader.
3. Activate the plugin via the WordPress admin dashboard or WP-CLI (`wp plugin activate course-discovery`).
4. The custom table `wp_course_index` will be created automatically upon activation.

## Usage

Use the shortcode on any page to render the interactive Course Finder UI:

```html
[course_finder per_page="12" order_by="name" order="ASC"]
```

## Architecture

```
src/
├── Domain/         # Core business rules, Entities (Course), Value Objects (Price, StartMonth).
├── Application/    # Orchestration layer, Services (CourseSearchService), Query DTOs.
├── Infrastructure/ # WordPress bindings, DB queries (WpCourseRepository), Filter Pipeline.
└── Presentation/   # REST API Controllers, Shortcodes.
```

### Data Flow

1. **Frontend:** User clicks a filter in the accessible UI. JS triggers `fetch()`.
2. **REST API:** `CourseSearchController` sanitises input and hydrates a `CourseSearchQuery` DTO.
3. **Application:** `CourseSearchService` builds a strictly typed `CourseSearchCriteria` object.
4. **Infrastructure:** `WpCourseRepository` passes criteria to the `FilterPipeline`.
5. **Database:** Filters append native SQL `MATCH...AGAINST` or `FIND_IN_SET` to a single compiled `$wpdb->prepare` statement. The query executes against the custom `wp_course_index` table.
6. **Domain:** DB rows are reconstructed into `Course` entities using Value Objects.

## Development & Testing

### Running Tests

We use PHPUnit. The suite covers Unit tests for the Domain and Application layers. Brain\Monkey is used to stub WordPress functions.

```bash
composer run test
```

### PHPCS

```bash
composer run lint
```
