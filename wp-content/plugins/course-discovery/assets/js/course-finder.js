/**
 * Course Discovery — Frontend Application
 *
 * Entry point: window.CourseDiscovery (injected by PHP shortcode)
 * Architecture: IIFE with three core classes
 *   - CourseDiscoveryApi   — fetch wrapper for the WP REST endpoints
 *   - MultiSelectCombobox  — WAI-ARIA 1.2 combobox/listbox multi-select
 *   - CheckboxFilter       — accessible checkbox multi-select
 *   - CourseFinder         — application orchestrator
 *
 * ARIA compliance:
 *   Comboboxes: trigger button has role="combobox", aria-expanded, aria-haspopup="listbox",
 *               aria-controls pointing to listbox. Listbox has role="listbox" and
 *               aria-multiselectable="true". Each option has role="option" + aria-selected.
 *   Keyboard:   Arrow Up/Down, Home, End navigate options. Space/Enter toggles selection.
 *               Escape closes the dropdown and returns focus to the trigger.
 *   Live region:#cf-announcer (role="status" aria-live="polite") announces result count changes.
 */
(function (window, document) {
  'use strict';

  // ── Config ──────────────────────────────────────────────────────────────────
  const cfg = window.CourseDiscovery || {};
  const REST_URL = (cfg.restUrl || '').replace(/\/$/, '');
  const i18n = cfg.i18n || {};

  // ── Utilities ────────────────────────────────────────────────────────────────

  function debounce(fn, delay) {
    let timer;
    return function (...args) {
      clearTimeout(timer);
      timer = setTimeout(() => fn.apply(this, args), delay);
    };
  }

  function esc(str) {
    const div = document.createElement('div');
    div.textContent = String(str || '');
    return div.innerHTML;
  }

  function uniqueId() {
    return 'cf-' + Math.random().toString(36).slice(2, 9);
  }

  // ── API Client ───────────────────────────────────────────────────────────────

  class CourseDiscoveryApi {
    constructor(baseUrl, nonce) {
      this.baseUrl = baseUrl;
      this.nonce = nonce;
    }

    async _fetch(endpoint, params = {}) {
      const url = new URL(this.baseUrl + endpoint, window.location.href);
      Object.entries(params).forEach(([key, value]) => {
        if (Array.isArray(value)) {
          // WordPress REST API expects array params as key[]=val
          value.forEach((v) => url.searchParams.append(key + '[]', v));
        } else if (value !== null && value !== undefined && value !== '') {
          url.searchParams.set(key, value);
        }
      });

      const resp = await fetch(url.toString(), {
        headers: { 'X-WP-Nonce': this.nonce },
      });

      if (!resp.ok) throw new Error(`API error: ${resp.status}`);
      return resp.json();
    }

    search(params) {
      return this._fetch('/courses', params);
    }

    getFilterOptions() {
      return this._fetch('/filter-options');
    }
  }

  // ── MultiSelectCombobox ─────────────────────────────────────────────────────
  //
  // Renders an accessible multi-select combobox following WAI-ARIA 1.2.
  // The trigger button uses role="combobox" with aria-expanded / aria-haspopup.
  // The dropdown uses role="listbox" + aria-multiselectable="true".
  // Options use role="option" + aria-selected.
  //
  // Keyboard behaviour:
  //   Space / Enter on closed trigger → open dropdown, focus first option
  //   Arrow Down                      → move focus to next option
  //   Arrow Up                        → move focus to previous option
  //   Home                            → focus first option
  //   End                             → focus last option
  //   Space / Enter on focused option → toggle selection
  //   Escape / Tab                    → close dropdown, return focus to trigger

  class MultiSelectCombobox {
    constructor({ container, label, options, onChange }) {
      this.container = container;
      this.label = label;
      this.options = options; // [{value, label}]
      this.onChange = onChange;
      this.selected = new Set();
      this.isOpen = false;
      this.activeIndex = -1;

      // Unique IDs for ARIA references
      this.btnId = uniqueId() + '-btn';
      this.listId = uniqueId() + '-list';
      this.labelId = uniqueId() + '-lbl';

      this._render();
      this._bindEvents();
    }

    _render() {
      this.container.innerHTML = `
        <div class="cf-combobox-wrapper">
          <span id="${this.labelId}" class="cf-filter-label">${esc(this.label)}</span>
          <button
            type="button"
            id="${this.btnId}"
            role="combobox"
            aria-expanded="false"
            aria-haspopup="listbox"
            aria-controls="${this.listId}"
            aria-labelledby="${this.labelId} ${this.btnId}"
            class="cf-combobox-trigger"
          >
            <span class="cf-combobox-text">${esc(i18n.allOptions || 'All')} ${esc(this.label)}</span>
            <svg class="cf-combobox-chevron" aria-hidden="true" focusable="false" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
            </svg>
          </button>
          <ul
            role="listbox"
            id="${this.listId}"
            aria-labelledby="${this.labelId}"
            aria-multiselectable="true"
            tabindex="-1"
            class="cf-listbox"
            hidden
          >
            ${this.options.map((opt, i) => `
              <li
                role="option"
                aria-selected="false"
                tabindex="-1"
                data-value="${esc(opt.value)}"
                data-index="${i}"
                class="cf-listbox-option"
              >
                <span class="cf-option-check" aria-hidden="true">
                  <svg viewBox="0 0 16 16" fill="currentColor" class="cf-check-icon" hidden>
                    <path d="M12.207 4.793a1 1 0 010 1.414l-5 5a1 1 0 01-1.414 0l-2-2a1 1 0 011.414-1.414L6.5 9.086l4.293-4.293a1 1 0 011.414 0z"/>
                  </svg>
                </span>
                <span class="cf-option-label">${esc(opt.label)}</span>
              </li>
            `).join('')}
          </ul>
        </div>
      `;

      this.btn = this.container.querySelector('.cf-combobox-trigger');
      this.list = this.container.querySelector('.cf-listbox');
      this.optionEls = [...this.container.querySelectorAll('[role="option"]')];
    }

    _bindEvents() {
      // Trigger: open/close on click
      this.btn.addEventListener('click', () => this._toggle());

      // Trigger keyboard
      this.btn.addEventListener('keydown', (e) => this._handleTriggerKeydown(e));

      // Options keyboard + click
      this.list.addEventListener('click', (e) => {
        const opt = e.target.closest('[role="option"]');
        if (opt) this._toggleOption(parseInt(opt.dataset.index, 10));
      });

      this.list.addEventListener('keydown', (e) => this._handleListKeydown(e));

      // Close on outside click
      document.addEventListener('click', (e) => {
        if (this.isOpen && !this.container.contains(e.target)) this._close();
      }, { capture: true });

      // Close on Escape bubbled up
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && this.isOpen) {
          this._close();
          this.btn.focus();
        }
      });
    }

    _handleTriggerKeydown(e) {
      if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        if (!this.isOpen) this._open();
        this._focusOption(0);
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (!this.isOpen) this._open();
        this._focusOption(this.optionEls.length - 1);
      }
    }

    _handleListKeydown(e) {
      const total = this.optionEls.length;
      if (total === 0) return;

      switch (e.key) {
        case 'ArrowDown':
          e.preventDefault();
          this._focusOption((this.activeIndex + 1) % total);
          break;
        case 'ArrowUp':
          e.preventDefault();
          this._focusOption((this.activeIndex - 1 + total) % total);
          break;
        case 'Home':
          e.preventDefault();
          this._focusOption(0);
          break;
        case 'End':
          e.preventDefault();
          this._focusOption(total - 1);
          break;
        case ' ':
        case 'Enter':
          e.preventDefault();
          if (this.activeIndex >= 0) this._toggleOption(this.activeIndex);
          break;
        case 'Tab':
        case 'Escape':
          e.preventDefault();
          this._close();
          this.btn.focus();
          break;
      }
    }

    _open() {
      this.isOpen = true;
      this.btn.setAttribute('aria-expanded', 'true');
      this.list.hidden = false;
    }

    _close() {
      this.isOpen = false;
      this.btn.setAttribute('aria-expanded', 'false');
      this.list.hidden = true;
      this.activeIndex = -1;
    }

    _toggle() {
      this.isOpen ? this._close() : this._open();
    }

    _focusOption(index) {
      if (!this.isOpen) this._open();
      this.activeIndex = index;
      this.optionEls[index]?.focus();
    }

    _toggleOption(index) {
      const opt = this.optionEls[index];
      if (!opt) return;

      const value = opt.dataset.value;
      if (this.selected.has(value)) {
        this.selected.delete(value);
        opt.setAttribute('aria-selected', 'false');
        opt.querySelector('.cf-check-icon').hidden = true;
        opt.classList.remove('cf-option--selected');
      } else {
        this.selected.add(value);
        opt.setAttribute('aria-selected', 'true');
        opt.querySelector('.cf-check-icon').hidden = false;
        opt.classList.add('cf-option--selected');
      }

      this._updateTriggerLabel();
      this.onChange([...this.selected]);
    }

    _updateTriggerLabel() {
      const count = this.selected.size;
      const textEl = this.btn.querySelector('.cf-combobox-text');
      if (count === 0) {
        textEl.textContent = `${i18n.allOptions || 'All'} ${this.label}`;
      } else {
        textEl.textContent = `${count} ${i18n.selected || 'selected'}`;
      }
    }

    // Public API
    getSelected() {
      return [...this.selected];
    }

    reset() {
      this.selected.clear();
      this.optionEls.forEach((opt) => {
        opt.setAttribute('aria-selected', 'false');
        opt.querySelector('.cf-check-icon').hidden = true;
        opt.classList.remove('cf-option--selected');
      });
      this._updateTriggerLabel();
    }
  }

  // ── CheckboxFilter ───────────────────────────────────────────────────────────
  // Accessible multi-select using standard checkbox inputs inside a disclosure.

  class CheckboxFilter {
    constructor({ container, label, options, onChange }) {
      this.container = container;
      this.label = label;
      this.options = options;
      this.onChange = onChange;
      this.selected = new Set();
      this.groupId = uniqueId();
      this._render();
      this._bindEvents();
    }

    _render() {
      this.container.innerHTML = `
        <fieldset class="cf-checkbox-filter" id="${this.groupId}-fs">
          <legend class="cf-filter-label">${esc(this.label)}</legend>
          <div class="cf-checkbox-list" role="group" aria-labelledby="${this.groupId}-lbl">
            ${this.options.map((opt) => {
              const id = `${this.groupId}-${esc(opt.value)}`;
              return `
                <label class="cf-checkbox-option" for="${id}">
                  <input
                    type="checkbox"
                    id="${id}"
                    name="${esc(this.label.toLowerCase())}"
                    value="${esc(opt.value)}"
                    class="cf-checkbox"
                  />
                  <span class="cf-checkbox-label">${esc(opt.label)}</span>
                </label>
              `;
            }).join('')}
          </div>
        </fieldset>
      `;
    }

    _bindEvents() {
      this.container.querySelectorAll('.cf-checkbox').forEach((cb) => {
        cb.addEventListener('change', () => {
          if (cb.checked) {
            this.selected.add(cb.value);
          } else {
            this.selected.delete(cb.value);
          }
          this.onChange([...this.selected]);
        });
      });
    }

    getSelected() {
      return [...this.selected];
    }

    reset() {
      this.selected.clear();
      this.container.querySelectorAll('.cf-checkbox').forEach((cb) => {
        cb.checked = false;
      });
    }
  }

  // ── Course Card ─────────────────────────────────────────────────────────────

  function renderCourseCard(course) {
    const priceHtml = course.price.is_free
      ? `<span class="cf-badge cf-badge--free">${esc(i18n.free || 'Free')}</span>`
      : `<span class="cf-badge cf-badge--paid">${esc(course.price.formatted)}</span>`;

    const thumbnailHtml = course.thumbnail_url
      ? `<img src="${esc(course.thumbnail_url)}" alt="" class="cf-card-img" loading="lazy" aria-hidden="true"/>`
      : `<div class="cf-card-img-placeholder" aria-hidden="true">
           <svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.5">
             <rect x="6" y="6" width="36" height="36" rx="4"/>
             <circle cx="18" cy="18" r="4"/><path d="m6 30 10-10 8 8 6-6 12 12"/>
           </svg>
         </div>`;

    const locationsHtml = course.locations.length
      ? course.locations.map(loc =>
          `<span class="cf-tag cf-tag--location">${esc(loc)}</span>`
        ).join('')
      : '';

    const nextDate = course.next_start_date
      ? `<span class="cf-tag cf-tag--date">${esc(course.next_start_date)}</span>`
      : '';

    return `
      <article class="cf-card" role="listitem">
        <a href="${esc(course.permalink)}" class="cf-card-link" tabindex="-1" aria-hidden="true">
          <div class="cf-card-media">${thumbnailHtml}</div>
        </a>

        <div class="cf-card-body">
          <header class="cf-card-header">
            <h2 class="cf-card-title">
              <a href="${esc(course.permalink)}" class="cf-card-title-link">
                ${esc(course.name)}
              </a>
            </h2>
            <div class="cf-card-price" aria-label="${esc(course.price.formatted)}">${priceHtml}</div>
          </header>

          ${course.short_description
            ? `<p class="cf-card-desc">${esc(course.short_description)}</p>`
            : ''}

          <div class="cf-card-tags" aria-label="Location and start date">
            ${locationsHtml}
            ${nextDate}
          </div>

          <footer class="cf-card-footer">
            <a
              href="${esc(course.permalink)}"
              class="cf-card-cta"
              aria-label="${esc(i18n.viewCourse || 'View Course')}: ${esc(course.name)}"
            >${esc(i18n.viewCourse || 'View Course')}
              <svg aria-hidden="true" focusable="false" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M3 10a.75.75 0 01.75-.75h10.638L10.23 5.29a.75.75 0 111.04-1.08l4.5 4.75a.75.75 0 010 1.08l-4.5 4.75a.75.75 0 11-1.04-1.08l4.158-3.96H3.75A.75.75 0 013 10z" clip-rule="evenodd"/>
              </svg>
            </a>
          </footer>
        </div>
      </article>
    `;
  }

  // ── Skeleton & States ────────────────────────────────────────────────────────

  function renderSkeletonCards(count) {
    return Array.from({ length: count }, () => `
      <div class="cf-card cf-card--skeleton" role="listitem" aria-hidden="true">
        <div class="cf-card-media cf-skeleton-pulse"></div>
        <div class="cf-card-body">
          <div class="cf-skeleton-line cf-skeleton-title cf-skeleton-pulse"></div>
          <div class="cf-skeleton-line cf-skeleton-pulse"></div>
          <div class="cf-skeleton-line cf-skeleton-short cf-skeleton-pulse"></div>
        </div>
      </div>
    `).join('');
  }

  function renderEmptyState() {
    return `
      <div class="cf-empty-state" role="listitem">
        <svg aria-hidden="true" focusable="false" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="1.5">
          <circle cx="32" cy="32" r="28"/>
          <path d="M22 32h20M32 22v20"/>
        </svg>
        <p>${esc(i18n.noResults || 'No courses found. Try adjusting your filters.')}</p>
      </div>
    `;
  }

  // ── Pagination ───────────────────────────────────────────────────────────────

  function renderPagination(total, page, totalPages, onPageChange) {
    if (totalPages <= 1) return '';

    const prevDisabled = page <= 1;
    const nextDisabled = page >= totalPages;

    const pageButtons = [];
    const delta = 2;
    const range = [];
    for (let i = Math.max(2, page - delta); i <= Math.min(totalPages - 1, page + delta); i++) {
      range.push(i);
    }
    if (page - delta > 2) range.unshift('…');
    if (page + delta < totalPages - 1) range.push('…');
    range.unshift(1);
    if (totalPages > 1) range.push(totalPages);

    range.forEach((p) => {
      if (p === '…') {
        pageButtons.push(`<span class="cf-page-ellipsis" aria-hidden="true">…</span>`);
      } else {
        const isCurrent = p === page;
        pageButtons.push(`
          <button
            type="button"
            class="cf-page-btn${isCurrent ? ' cf-page-btn--current' : ''}"
            data-page="${p}"
            ${isCurrent ? 'aria-current="page"' : ''}
            aria-label="${esc(i18n.page || 'Page')} ${p}"
          >${p}</button>
        `);
      }
    });

    return `
      <div class="cf-pagination" role="navigation" aria-label="${esc(i18n.page || 'Page')} ${page} ${esc(i18n.of || 'of')} ${totalPages}">
        <button
          type="button"
          class="cf-page-btn cf-page-btn--prev"
          data-page="${page - 1}"
          ${prevDisabled ? 'disabled aria-disabled="true"' : ''}
          aria-label="${esc(i18n.previous || 'Previous page')}"
        >
          <svg aria-hidden="true" focusable="false" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M17 10a.75.75 0 01-.75.75H5.612l4.158 3.96a.75.75 0 11-1.04 1.08l-4.5-4.75a.75.75 0 010-1.08l4.5-4.75a.75.75 0 111.04 1.08L5.612 9.25H16.25A.75.75 0 0117 10z" clip-rule="evenodd"/>
          </svg>
        </button>

        ${pageButtons.join('')}

        <button
          type="button"
          class="cf-page-btn cf-page-btn--next"
          data-page="${page + 1}"
          ${nextDisabled ? 'disabled aria-disabled="true"' : ''}
          aria-label="${esc(i18n.next || 'Next page')}"
        >
          <svg aria-hidden="true" focusable="false" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M3 10a.75.75 0 01.75-.75h10.638L10.23 5.29a.75.75 0 111.04-1.08l4.5 4.75a.75.75 0 010 1.08l-4.5 4.75a.75.75 0 11-1.04-1.08l4.158-3.96H3.75A.75.75 0 013 10z" clip-rule="evenodd"/>
          </svg>
        </button>
      </div>
    `;
  }

  // ── CourseFinder App ─────────────────────────────────────────────────────────

  class CourseFinder {
    constructor(container) {
      this.container = container;
      this.api = new CourseDiscoveryApi(REST_URL, cfg.nonce || '');
      this.filterComponents = {}; // key → ComboboxFilter | CheckboxFilter
      this.currentPage = 1;
      this.perPage = cfg.perPage || 12;
      this.orderBy = cfg.orderBy || 'name';
      this.order = cfg.order || 'ASC';
      this.isLoading = false;

      // DOM refs
      this.form = container.querySelector('#cf-search-form');
      this.searchInput = container.querySelector('#cf-text-search');
      this.filterBar = container.querySelector('#cf-filter-bar');
      this.grid = container.querySelector('#cf-results-grid');
      this.pagination = container.querySelector('#cf-pagination');
      this.summary = container.querySelector('#cf-results-summary');
      this.announcer = document.getElementById('cf-announcer');
    }

    async init() {
      try {
        const options = await this.api.getFilterOptions();
        this._renderFilterControls(options);
        this._bindFormEvents();
        await this._search();
      } catch (err) {
        console.error('[CourseDiscovery] init error:', err);
        this._renderError();
      }
    }

    // ── Filter rendering ──────────────────────────────────────────────────────

    _renderFilterControls(options) {
      const slots = this.filterBar.querySelectorAll('.cf-filter-slot');

      slots.forEach((slot) => {
        const filterKey = slot.dataset.filter;
        const label = slot.dataset.label || filterKey;
        const type = slot.dataset.type; // 'combobox' | 'checkbox'

        let filterOptions = [];

        switch (filterKey) {
          case 'providers':
            filterOptions = (options.providers || []).map((p) => ({
              value: String(p.id),
              label: p.name,
            }));
            break;

          case 'locations':
            filterOptions = (options.locations || []).map((loc) => ({
              value: loc,
              label: loc,
            }));
            break;

          case 'start_dates':
            filterOptions = (options.start_dates || []).map((d) => ({
              value: d,
              label: d,
            }));
            break;

          case 'categories':
            filterOptions = (options.categories || []).map((cat) => ({
              value: String(cat.id),
              label: cat.name,
            }));
            break;
        }

        if (filterOptions.length === 0) {
          slot.hidden = true;
          return;
        }

        const onChange = () => {
          this.currentPage = 1;
          this._debouncedSearch();
        };

        if (type === 'combobox') {
          this.filterComponents[filterKey] = new MultiSelectCombobox({
            container: slot,
            label,
            options: filterOptions,
            onChange,
          });
        } else {
          this.filterComponents[filterKey] = new CheckboxFilter({
            container: slot,
            label,
            options: filterOptions,
            onChange,
          });
        }
      });
    }

    // ── Event binding ─────────────────────────────────────────────────────────

    _bindFormEvents() {
      this._debouncedSearch = debounce(() => this._search(), 400);

      // Live search on text input
      this.searchInput.addEventListener('input', () => {
        this.currentPage = 1;
        this._debouncedSearch();
      });

      // Form submit prevents reload, triggers immediate search
      this.form.addEventListener('submit', (e) => {
        e.preventDefault();
        this.currentPage = 1;
        this._search();
      });

      // Pagination delegation
      this.pagination.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-page]');
        if (!btn || btn.disabled) return;
        const page = parseInt(btn.dataset.page, 10);
        if (!isNaN(page) && page !== this.currentPage) {
          this.currentPage = page;
          this._search().then(() => {
            // Scroll to top of results
            this.grid.scrollIntoView({ behavior: 'smooth', block: 'start' });
          });
        }
      });
    }

    // ── Search ────────────────────────────────────────────────────────────────

    async _search() {
      if (this.isLoading) return;
      this._setLoading(true);

      try {
        const params = this._getParams();
        const result = await this.api.search(params);
        this._renderResults(result);
      } catch (err) {
        console.error('[CourseDiscovery] search error:', err);
        this._renderError();
      } finally {
        this._setLoading(false);
      }
    }

    _getParams() {
      const params = {
        page: this.currentPage,
        per_page: this.perPage,
        order_by: this.orderBy,
        order: this.order,
      };

      const searchVal = this.searchInput?.value?.trim();
      if (searchVal) params.search = searchVal;

      const providers = this.filterComponents['providers']?.getSelected() || [];
      const locations = this.filterComponents['locations']?.getSelected() || [];
      const startDates = this.filterComponents['start_dates']?.getSelected() || [];
      const categories = this.filterComponents['categories']?.getSelected() || [];

      if (providers.length) params.providers = providers;
      if (locations.length) params.locations = locations;
      if (startDates.length) params.start_dates = startDates;
      if (categories.length) params.categories = categories;

      return params;
    }

    // ── Rendering ─────────────────────────────────────────────────────────────

    _renderResults(result) {
      const { courses, total, page, total_pages } = result;

      // Summary
      this.summary.textContent = `${total} ${i18n.resultCount || 'courses found'}`;

      // Announce to screen readers
      if (this.announcer) {
        this.announcer.textContent = '';
        requestAnimationFrame(() => {
          this.announcer.textContent = `${total} ${i18n.resultCount || 'courses found'}`;
        });
      }

      // Grid
      this.grid.setAttribute('aria-busy', 'false');
      if (!courses || courses.length === 0) {
        this.grid.innerHTML = renderEmptyState();
      } else {
        this.grid.innerHTML = courses.map(renderCourseCard).join('');
      }

      // Pagination
      this.pagination.innerHTML = renderPagination(
        total,
        page,
        total_pages,
        (p) => { this.currentPage = p; this._search(); }
      );
    }

    _renderError() {
      this.grid.setAttribute('aria-busy', 'false');
      this.grid.innerHTML = `
        <div class="cf-error-state" role="listitem">
          <p>Something went wrong. Please try again.</p>
        </div>
      `;
    }

    _setLoading(loading) {
      this.isLoading = loading;
      this.grid.setAttribute('aria-busy', loading ? 'true' : 'false');
      if (loading) {
        this.grid.innerHTML = renderSkeletonCards(this.perPage > 6 ? 6 : this.perPage);
      }
    }
  }

  // ── Bootstrap ────────────────────────────────────────────────────────────────

  function boot() {
    const app = document.getElementById('course-finder-app');
    if (app) {
      new CourseFinder(app).init();
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})(window, document);
