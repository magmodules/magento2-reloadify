require([
    'jquery',
    'Magento_Ui/js/modal/modal',
    'loader'
], function ($, modal) {

    /**
     * Response data is text, never markup: log lines, test messages and version numbers come from the
     * server as-is.
     *
     * @param{*} value
     * @returns {String}
     */
    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (char) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[char];
        });
    }

    /**
     * The changelog is HTML fetched from the version service. Only the tags the changelog format uses
     * survive; other elements are reduced to their text, scripts and the like are dropped, and no
     * attribute gets through.
     */
    var CHANGELOG_TAGS = ['b', 'strong', 'i', 'em', 'u', 'br', 'p', 'ul', 'ol', 'li'];
    var CHANGELOG_DROP = ['script', 'style', 'template', 'iframe', 'object', 'embed', 'svg', 'math'];

    /**
     * @param{String} html
     * @returns {String}
     */
    function sanitizeChangelog(html) {
        var body = new DOMParser().parseFromString(String(html == null ? '' : html), 'text/html').body;

        var clean = function (node) {
            Array.prototype.slice.call(node.childNodes).forEach(function (child) {
                if (child.nodeType === Node.TEXT_NODE) {
                    return;
                }
                if (child.nodeType !== Node.ELEMENT_NODE || CHANGELOG_DROP.indexOf(child.localName) !== -1) {
                    child.remove();
                    return;
                }
                if (CHANGELOG_TAGS.indexOf(child.localName) === -1) {
                    child.replaceWith(document.createTextNode(child.textContent));
                    return;
                }
                Array.prototype.slice.call(child.attributes).forEach(function (attribute) {
                    child.removeAttribute(attribute.name);
                });
                clean(child);
            });
        };
        clean(body);

        return body.innerHTML;
    }

    /**
     * @param{String} modalSelector - modal css selector.
     * @param{Object} options - modal options.
     */
    function initModal(modalSelector, options) {
        var $resultModal = $(modalSelector);

        if (!$resultModal.length) return;

        var popup = modal(options, $resultModal);
        $resultModal.loader({texts: ''});
    }

    var successHandlers = {
        /**
         * @param{Object[]} result - Ajax request response data.
         * @param{Object} $container - jQuery container element.
         */
        debug: function (result, $container) {

            if (Array.isArray(result)) {

                var lisHtml = result.map(function (err) {
                    return '<li class="magmodules-reloadify-result_debug-item"><strong>' + escapeHtml(err.date)
                        + '</strong><p>' + escapeHtml(err.msg) + '</p></li>';
                }).join('');

                $container.find('.result').empty().append('<ul>' + lisHtml + '</ul>');
            } else {

                $container.find('.result').empty().text(String(result));
            }
        },

        /**
         * @param{Object[]} result - Ajax request response data.
         * @param{Object} $container - jQuery container element.
         */
        error: function (result, $container) {

            if (Array.isArray(result)) {

                var lisHtml = result.map(function (err) {
                    return '<li class="magmodules-reloadify-result_error-item"><strong>' + escapeHtml(err.date)
                        + '</strong><p>' + escapeHtml(err.msg) + '</p></li>';
                }).join('');

                $container.find('.result').empty().append('<ul>' + lisHtml + '</ul>');
            } else {

                $container.find('.result').empty().text(String(result));
            }
        },

        /**
         * @param{Object[]} result - Ajax request response data.
         * @param{Object} $container - jQuery container element.
         */
        test: function (result, $container) {

            var lisHtml = result.map(function (test) {

                var supportLinkHtml = test.support_link ?
                    '<a target="_blank" href="' + escapeHtml(test.support_link)
                    + '" class="magmodules-reloadify-icon__help-rounded"></a>' : '';
                var resultText = test.result_code === 'success' ?
                    $.mage.__('Passed') : $.mage.__('Failed');
                var resultMsg = test.result_code === 'failed' ? test.result_msg : '';

                return '<li class="magmodules-reloadify-result_test-item ' + escapeHtml(test.result_code)
                    + '"><strong>' + resultText + '</strong>'
                    + '<div><p>' + escapeHtml(test.test) + '</p><p><em>' + escapeHtml(resultMsg) + '</em></p></div>'
                    + supportLinkHtml + '</li>';
            }).join('');

            $container.find('.result').empty().append(lisHtml);
        },

        /**
         * @param{Object[]} result - Ajax request response data.
         * @param{Object} $container - jQuery container element.
         */
        version: function (result, $container) {

            var resultHtml = '';
            var currentVersion = result.current_verion.replace(/v|version/gi, '');
            var latestVersion = result.last_version.replace(/v|version/gi, '');
            if (!this.isNewerVersion(latestVersion, currentVersion)) {
                resultHtml = '<strong class="magmodules-reloadify-version magmodules-reloadify-icon__thumbs-up">'
                    + $.mage.__('Great, you are using the latest version.')
                    + '</strong>';
            } else {

                var translatedResult = $.mage.__('There is a new version available <span>(%1)</span> see <button type="button" id="magmodules-reloadify-button_changelog">changelog</button>.')
                    .replace('%1', escapeHtml(latestVersion));

                resultHtml = '<strong class="magmodules-reloadify-version magmodules-reloadify-icon__thumbs-down">'
                    + translatedResult
                    + '</strong>';
            }

            $container.html(resultHtml);
        },

        isNewerVersion: function(latest, current) {
            var latestParts = latest.split('.').map(Number);
            var currentParts = current.split('.').map(Number);
            var len = Math.max(latestParts.length, currentParts.length);
            for (var i = 0; i < len; i++) {
                var l = latestParts[i] || 0;
                var c = currentParts[i] || 0;
                if (l > c) {
                    return true;
                }
                if (l < c) {
                    return false;
                }
            }
            return false;
        },

        /**
         * @param{Object[]} result - Ajax request response data.
         * @param{Object} $container - jQuery container element.
         */
        changelog: function (result, $container) {

            var lisHtml = Object.keys(result).map(function (key) {

                var version = key;
                var date = result[key].date;
                var resultHtml = result[key].changelog;

                return '<li class="magmodules-reloadify-result_changelog-item"><b>'
                    + escapeHtml(version) + '</b><span class="magmodules-reloadify-divider">|</span><b>'
                    + escapeHtml(date) + '</b><div>'
                    + sanitizeChangelog(resultHtml) + '</div></li>';
            }).join('');

            $container.find('.result').empty().append(lisHtml);
        },
    }

    // init debug modal
    initModal('#magmodules-reloadify-result_debug-modal', {
        type: 'popup',
        responsive: true,
        innerScroll: true,
        title: $.mage.__('last 100 debug log lines'),
        buttons: [
            {
                text: $.mage.__('download as .txt file'),
                class: 'magmodules-reloadify-button__download magmodules-reloadify-icon__download-alt',
                click: function () {

                    var elText = document.getElementById('magmodules-reloadify-result_debug').innerText || '';
                    var link = document.createElement('a');

                    link.setAttribute('download', 'debug-log.txt');
                    link.setAttribute('href', 'data:text/plain;charset=utf-8,' + encodeURIComponent(elText));
                    link.click();
                },
            },
            {
                text: $.mage.__('ok'),
                class: '',
                click: function () {
                    this.closeModal();
                },
            }
        ]
    });

    // init error modal
    initModal('#magmodules-reloadify-result_error-modal', {
        type: 'popup',
        responsive: true,
        innerScroll: true,
        title: $.mage.__('last 100 error log records'),
        buttons: [
            {
                text: $.mage.__('download as .txt file'),
                class: 'magmodules-reloadify-button__download magmodules-reloadify-icon__download-alt',
                click: function () {

                    var elText = document.getElementById('magmodules-reloadify-result_error').innerText || '';
                    var link = document.createElement('a');

                    link.setAttribute('download', 'error-log.txt');
                    link.setAttribute('href', 'data:text/plain;charset=utf-8,' + encodeURIComponent(elText));
                    link.click();
                },
            },
            {
                text: $.mage.__('ok'),
                class: '',
                click: function () {
                    this.closeModal();
                },
            }
        ]
    });

    // init selftest modal
    initModal('#magmodules-reloadify-result_test-modal', {
        type: 'popup',
        responsive: true,
        innerScroll: true,
        title: $.mage.__('Self-test'),
        buttons: [
            {
                text: $.mage.__('ok'),
                class: '',
                click: function () {
                    this.closeModal();
                },
            }
        ]
    });

    // init changelog modal
    initModal('#magmodules-reloadify-result_changelog-modal', {
        type: 'popup',
        responsive: true,
        innerScroll: true,
        title: 'Changelog',
        buttons: [
            {
                text: $.mage.__('ok'),
                class: '',
                click: function () {
                    this.closeModal();
                },
            }
        ]
    });

    // init loader on the Check Version block
    $('.magmodules-reloadify-result_version-wrapper').loader({texts: ''});

    /**
     * Ajax request event
     */
    $(document).on('click', '[id^=magmodules-reloadify-button]', function () {
        var actionName = this.id.split('_')[1];
        var $modal = $('#magmodules-reloadify-result_' + actionName + '-modal');
        var $result = $('#magmodules-reloadify-result_' + actionName);

        if (actionName === 'version') {
            $(this).fadeOut(300).addClass('magmodules-reloadify-disabled');
            $modal = $('.magmodules-reloadify-result_' + actionName + '-wrapper');
            $modal.loader('show');
        } else {
            $modal.modal('openModal').loader('show');
        }

        $result.hide();

        new Ajax.Request($modal.data('magmodules-reloadify-endpoind-url'), {
            loaderArea: false,
            asynchronous: true,
            onSuccess: function (response) {

                if (response.status > 200) {
                    var result = response.statusText;
                } else {
                    successHandlers[actionName](response.responseJSON.result || response.responseJSON, $result);

                    $result.fadeIn();
                    $modal.loader('hide');
                }
            }
        });
    });
});
