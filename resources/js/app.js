import { createPopper } from '@popperjs/core';
import './bootstrap';
// flatpickr
import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';

import $ from 'jquery';
import select2 from 'select2';

window.jQuery = window.$ = $;
select2();

window.createPopper = createPopper;
window.flatpickr = flatpickr;


// Register Alpine.js components on alpine:init
document.addEventListener('alpine:init', () => {
    window.Alpine.data("dropdown", () => ({
        open: false,
        toggle() {
            this.open = !this.open;
            if (this.open) this.position();
        },
        position() {
            this.$nextTick(() => {
                const button = this.$el;
                const dropdown = this.$refs.dropdown;
                const rect = button.getBoundingClientRect();
                
                // Apply initial styles to ensure measurement is possible
                dropdown.style.position = "fixed";
                dropdown.style.zIndex = "999";
                dropdown.style.right = `${window.innerWidth - rect.right - 13}px`;

                const dropdownHeight = Math.max(dropdown.offsetHeight, 0);
                const spaceBelow = window.innerHeight - rect.bottom;
                const spaceAbove = rect.top;

                if (spaceBelow < dropdownHeight && spaceAbove > spaceBelow) {
                    dropdown.style.top = `${rect.top - dropdownHeight}px`;
                } else {
                    dropdown.style.top = `${rect.bottom}px`;
                }
            });
        },
        init() {
            this.$watch("open", (value) => {
                if (value) this.position();
            });
        },
    }));
});

function initPageEnhancements() {
    // Initialize Select2 on all select elements
    initSelect2();
}

window.initSelect2 = function (scope = document) {
    if (typeof window.jQuery === 'undefined' || typeof window.jQuery.fn.select2 === 'undefined') {
        return;
    }

    const $ = window.jQuery;
    const $target = $(scope);
    const selects = $target.is('select') ? $target : $target.find('select');

    selects.each(function () {
        const $select = $(this);

        // Skip if marked with no-select2 class
        if ($select.hasClass('no-select2')) {
            return;
        }

        // If already initialized, sync display safely if value changed
        if ($select.hasClass('select2-hidden-accessible')) {
            if (!$select.data('is-dispatching')) {
                $select.data('is-dispatching', true);
                $select.trigger('change.select2');
                $select.data('is-dispatching', false);
            }
            return;
        }

        // Determine modal parent if inside a modal dialog to prevent z-index issues
        const $modal = $select.closest('.modal, [x-ref="modal"], [role="dialog"], .fixed');
        const dropdownParent = $modal.length ? $modal : $(document.body);

        // Initialize Select2
        $select.select2({
            width: '100%',
            dropdownParent: dropdownParent,
            placeholder: $select.attr('placeholder') || $select.find('option[value=""]').text() || 'Select option',
            allowClear: false
        });

        // Sync with Livewire wire:model & native change events safely without recursive loops
        $select.off('change.select2-sync').on('change.select2-sync', function () {
            if ($select.data('is-dispatching')) {
                return;
            }
            $select.data('is-dispatching', true);

            const event = new Event('change', { bubbles: true });
            this.dispatchEvent(event);

            $select.data('is-dispatching', false);
        });
    });
};

document.addEventListener('DOMContentLoaded', () => window.initSelect2());
document.addEventListener('livewire:navigated', () => {
    initPageEnhancements();
    window.initSelect2();
});

// Watch for Livewire updates
document.addEventListener('livewire:initialized', () => {
    if (window.Livewire) {
        window.Livewire.hook('morph.updated', () => window.initSelect2());
    }
});

// Auto-open native date/time picker dialog when clicking anywhere on the input field
document.addEventListener('click', (e) => {
    const dateInput = e.target.closest('input[type="date"], input[type="time"], input[type="datetime-local"], input[type="month"]');
    if (dateInput && typeof dateInput.showPicker === 'function' && !dateInput.disabled && !dateInput.readOnly) {
        try {
            dateInput.showPicker();
        } catch (err) {
            // Ignore error if picker cannot be opened or is already open
        }
    }
});
