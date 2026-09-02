/**
 * Thiscovery Translate — language picker + response translate helpers.
 * (No inline handlers — HumHub CSP uses nonces and blocks onchange= attributes.)
 */
(function ($) {
    function bindLanguagePicker(root) {
        $(root).find('select.tt-lang-picker__select').off('change.ttLang').on('change.ttLang', function () {
            var form = this.form;
            if (form) {
                form.submit();
            }
        });
    }

    function init() {
        bindLanguagePicker(document);
    }

    if (window.humhub && humhub.modules) {
        // Prefer HumHub module lifecycle when available
        try {
            humhub.modules.require('ui.view', function () {
                init();
            });
        } catch (e) {
            // fall through
        }
    }

    $(init);
    $(document).on('humhub:ready afterAjaxContentLoad', init);

    $(document).on('click', '.tt-translate-response', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var url = $btn.data('url');
        if (!url) {
            return;
        }
        $btn.prop('disabled', true);
        $.ajax({
            url: url,
            method: 'POST',
            dataType: 'json',
            data: {
                answer_field_id: $btn.data('answer-field-id'),
                original_text: $btn.data('original'),
                response_language: $btn.data('response-language'),
                target_language: $btn.data('target-language')
            }
        }).done(function (res) {
            if (!res || !res.success) {
                return;
            }
            var $slot = $('.tt-response-translated[data-for="' + $btn.data('answer-field-id') + '"]');
            $slot.html('<small>Translated</small><br>' + $('<div/>').text(res.translated || '').html().replace(/\n/g, '<br>')).show();
        }).always(function () {
            $btn.prop('disabled', false);
        });
    });

    // Admin: expand / collapse settings accordions
    $(document).on('click', '[data-tt-acc-all]', function () {
        var open = $(this).data('tt-acc-all') === 'open';
        $('.tt-admin details.tt-set-acc').each(function () {
            this.open = open;
        });
    });

    // Admin: inline ? guidance panels
    $(document).on('click', '[data-tt-guide-toggle]', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var id = $btn.attr('aria-controls');
        var $panel = id ? $('#' + id) : $();
        if (!$panel.length) {
            return;
        }
        var expanded = $btn.attr('aria-expanded') === 'true';
        $btn.attr('aria-expanded', expanded ? 'false' : 'true');
        $panel.prop('hidden', expanded);
    });
})(window.jQuery || window.$);
