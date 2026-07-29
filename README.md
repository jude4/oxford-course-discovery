# Course Discovery Plugin

A strictly typed, scalable, and highly accessible Course Discovery system for WordPress. Designed using Domain-Driven Design (DDD) principles and custom database indexing to avoid the performance pitfalls of standard `WP_Query` and meta-queries.

## Submission Requirements Checklist

### Environment Requirements
- PHP 8.1+
- WordPress 6.0+
- Composer (for dependency management)
- **Zero External Plugins Required** (ACF is NOT required; uses custom native WordPress Meta Boxes)

### Setup Instructions
1. Clone this repository to your local machine.
2. Start the local WordPress environment by running: `docker-compose up -d`
3. Install the plugin's PHP dependencies by running the composer container: `docker-compose --profile tools up composer`
4. The site will now be available at `http://localhost:8080` (log in with `wordpress` / `wordpress`).
5. Activate the **Course Discovery** plugin via the WordPress admin dashboard.
6. (Optional) Seed the database with dummy courses, providers, and categories by running:
   `docker-compose exec wordpress wp eval-file wp-content/plugins/course-discovery/bin/generate-dummy-data.php`

### Database Setup
The plugin automatically provisions a custom index table `wp_course_index` upon activation (via `register_activation_hook`). This table is a flattened, denormalised representation of courses and their relationships, designed specifically for high-speed multi-faceted filtering.
A `CourseSyncListener` keeps this table in perfect sync with the canonical WordPress `wp_posts` and `wp_postmeta` tables by listening to native `save_post` and `updated_post_meta` hooks.

### Development Commands
- **Testing:** `composer run test` (Runs PHPUnit test suite)

### Architectural Decisions
1. **Domain-Driven Design (DDD):** Core business logic lives in `src/Domain`, entirely decoupled from WordPress internals. Uses Value Objects for precise validation (`StartMonth`, `Price`, `CourseId`).
2. **Custom Search Index:** We bypassed `WP_Query` for the search operation because combining `meta_query` and `tax_query` leads to severe performance degradation via heavy `JOIN` operations.
3. **Strict Typing:** `declare(strict_types=1);` used universally. Full parameter and return type coverage.
4. **WAI-ARIA 1.2 Frontend:** The React-free, vanilla JS frontend uses semantic HTML and the WAI-ARIA 1.2 Combobox pattern for complex multi-select dropdowns. Fully navigable via keyboard with live screen reader announcements.
5. **Extensible Pipeline:** The search query generation uses a `FilterPipeline` design pattern. Third-party plugins can inject custom filters (e.g. `AuthorFilter`, `DurationFilter`) via WordPress hooks without altering core code.

### Assumptions Made During Implementation
1. **Dates:** We assumed "Month-Year format" meant retaining recurring start months rather than a specific calendar day. We modelled this using a `StartMonth` value object (YYYY-MM).
2. **Provider Locations:** We assumed locations are derived exclusively from the Provider's profile rather than assigned directly to courses. If a provider's location changes, it automatically cascades to all associated courses.
3. **Prices:** We modelled prices as singular numeric values, but built the `Price` Value Object so it could be easily extended into a `PriceRange` object in the future without breaking the `Course` entity contract.

## Testing Instructions & Strategy

### What should be tested
- **Domain Logic:** Value Objects (`Price`, `StartMonth`) parsing and validation.
- **Query Building:** The `FilterPipeline` appending the correct SQL clauses.
- **Sync Logic:** `CourseSyncListener` correctly parsing native post meta data into a `CourseIndexRecord`.

### High-risk areas
- The synchronisation between WordPress standard tables and `wp_course_index`. If `CourseSyncListener` fails, the search index goes out of sync.
- The WAI-ARIA frontend state management, as focus-trapping and keyboard navigation must meet accessibility standards.

### Regression prevention strategy
- We use `Brain\Monkey` for Unit Tests in the Domain and Application layers to assert business logic in isolation without loading the WordPress bootstrap.
- Strict typing prevents the majority of runtime type errors.

### How new filters can be tested consistently
New filters (implementing `FilterInterface`) can be tested via Unit Tests by passing a mock `$wpdb` instance and asserting that the `apply()` method appends the expected SQL `WHERE` clause without executing an actual database query.

## Performance & Scalability

### Expected performance bottlenecks & WordPress Meta Query Limitations
Standard `WP_Query` with multiple `meta_query` conditions generates multiple `INNER JOIN`s on the `wp_postmeta` table. As the database grows to thousands of courses, these joins become exponentially slower, leading to table scans and high CPU usage.

### Indexing Considerations & Query Performance
To solve the meta-query bottleneck, we implemented the `wp_course_index` table.
- **Denormalisation:** Array relationships (Providers, Instructors, Categories) are stored as comma-separated strings.
- **Querying:** We use `FIND_IN_SET()` and `MATCH() AGAINST()` for high-speed lookups on a single table. No joins are required.
- **Indexes:** The table uses a `FULLTEXT` index on `name`, `short_description`, and `long_description` for fast keyword searches.

### Caching Opportunities
- The REST API endpoint `/wp-json/course-discovery/v1/courses` can be heavily cached using Redis or Varnish.
- Since search parameters are passed in the GET query string, edge-caching (Cloudflare) is highly effective.
- Database results can be cached via `wp_cache_set` using a hashed query string as the transient key.

### Pagination Strategy
Pagination is handled at the SQL level via `LIMIT` and `OFFSET`. The `SQL_CALC_FOUND_ROWS` approach is avoided due to deprecation; instead, we run a separate lightweight `SELECT COUNT(post_id)` query to calculate `total_pages`.

### Evolving to support hundreds of thousands (or millions) of courses
If the platform scales to millions of courses, the current `wp_course_index` structure (using `FIND_IN_SET`) will eventually hit performance limits. At that stage, we would:
1. **Dedicated Lookup Tables:** Move relationships into dedicated many-to-many pivot tables (e.g., `course_providers_lookup`) to utilise B-Tree indexing.
2. **External Search Technologies:** Offload the search entirely to **Elasticsearch**, **Typesense**, or **Algolia**. The `CourseSyncListener` would be refactored to push JSON payloads to the external search engine's API instead of writing to `wp_course_index`. The frontend JS would then query the external search engine directly, bypassing the WordPress server entirely.
