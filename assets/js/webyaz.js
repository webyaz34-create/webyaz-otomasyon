(function($) {
    'use strict';

    $(document).ready(function() {

        // ==================== CHECKOUT: Bireysel / Kurumsal ====================
        function toggleFields(type) {
            if (type === 'bireysel') {
                $('.webyaz-bireysel-field').slideDown(300);
                $('.webyaz-kurumsal-field').slideUp(300).find('input').val('');
            } else {
                $('.webyaz-kurumsal-field').slideDown(300);
                $('.webyaz-bireysel-field').slideUp(300).find('input').val('');
            }
        }

        $('.webyaz-kurumsal-field').hide();

        $('.webyaz-toggle-btn').on('click', function(e) {
            e.preventDefault();
            var type = $(this).data('type');
            $('.webyaz-toggle-btn').removeClass('active');
            $(this).addClass('active');
            $('#webyaz_customer_type').val(type);
            toggleFields(type);
        });

        $(document).on('input', 'input[name="webyaz_tc_kimlik"]', function() {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);
        });

        $(document).on('input', 'input[name="webyaz_vergi_no"]', function() {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);
        });

        // ==================== CHECKOUT: Hediye Secenekleri ====================
        $('#webyaz_is_gift').on('change', function() {
            if ($(this).is(':checked')) {
                $('#webyazGiftDetails').slideDown(300);
            } else {
                $('#webyazGiftDetails').slideUp(300);
                $('#webyazGiftDetails input[type="checkbox"]').prop('checked', false);
                $('#webyazGiftDetails textarea').val('');
            }
        });

        // ==================== WHATSAPP WIDGET ====================
        $('#webyazWaFab').on('click', function() {
            var popup = $('#webyazWaPopup');
            if (popup.is(':visible')) {
                popup.fadeOut(200);
            } else {
                popup.fadeIn(300);
            }
        });

        $('#webyazWaClose').on('click', function(e) {
            e.stopPropagation();
            $('#webyazWaPopup').fadeOut(200);
        });

        // ==================== STOK ALARM ====================
        $('#webyazStockBtn').on('click', function() {
            var email = $('#webyazStockEmail').val();
            var productId = $('#webyazStockForm').data('product');
            var $btn = $(this);
            var $msg = $('#webyazStockMsg');

            if (!email || email.indexOf('@') === -1) {
                $msg.html('<span style="color:#d32f2f;">Gecerli bir e-posta giriniz.</span>').show();
                return;
            }

            $btn.prop('disabled', true).text('Kaydediliyor...');

            $.post(webyaz_ajax.ajax_url, {
                action: 'webyaz_stock_alert',
                nonce: webyaz_ajax.nonce,
                email: email,
                product_id: productId
            }, function(response) {
                if (response.success) {
                    $msg.html('<span style="color:#22863a;">' + response.data + '</span>').show();
                    $btn.text('Kaydedildi!');
                } else {
                    $msg.html('<span style="color:#d32f2f;">' + response.data + '</span>').show();
                    $btn.prop('disabled', false).text('Stok Gelince Haber Ver');
                }
            });
        });

        // ==================== COMPARE ====================
        $(document).on('click', '.webyaz-compare-btn, .webyaz-compare-btn-single', function(e) {
            e.preventDefault();
            var pid = $(this).data('product-id');
            $.post(webyaz_ajax.ajax_url, {
                action: 'webyaz_compare_add',
                product_id: pid
            }, function(response) {
                if (response.success) {
                    $('#webyazCompareCount').text(response.data.count);
                    $('#webyazCompareBar').show();
                    location.reload();
                } else {
                    alert(response.data.message);
                }
            });
        });

        $(document).on('click', '.webyaz-compare-bar-remove', function(e) {
            e.preventDefault();
            var pid = $(this).data('product-id');
            $.post(webyaz_ajax.ajax_url, {
                action: 'webyaz_compare_remove',
                product_id: pid
            }, function(response) {
                if (response.success) {
                    if (response.data.count === 0) {
                        $('#webyazCompareBar').hide();
                    }
                    location.reload();
                }
            });
        });

        $('#webyazCompareClear').on('click', function() {
            document.cookie = 'webyaz_compare=[];path=/;max-age=0';
            $('#webyazCompareBar').hide();
            location.reload();
        });

        // ==================== WISHLIST ====================
        $(document).on('click', '.webyaz-wishlist-btn, .webyaz-wishlist-btn-single, .webyaz-wishlist-remove', function(e) {
            e.preventDefault();
            var $btn = $(this);
            var pid = $btn.data('product-id');
            $.post(webyaz_ajax.ajax_url, {
                action: 'webyaz_wishlist_toggle',
                product_id: pid
            }, function(response) {
                if (response.success) {
                    if ($btn.hasClass('webyaz-wishlist-remove')) {
                        $btn.closest('.webyaz-wishlist-item').fadeOut(300, function() { $(this).remove(); });
                    } else {
                        $btn.toggleClass('active');
                        var svg = $btn.find('svg');
                        if ($btn.hasClass('active')) {
                            svg.attr('fill', 'currentColor');
                        } else {
                            svg.attr('fill', 'none');
                        }
                    }
                }
            });
        });
    });

    // Fix: Flatsome ordering select width - replace with custom dropdown
    $(window).on('load', function() {
        var $sel = $('.woocommerce-ordering select');
        if (!$sel.length) return;
        var $wrap = $sel.closest('.woocommerce-ordering');
        var opts = [];
        $sel.find('option').each(function() {
            opts.push({val: $(this).val(), text: $(this).text(), selected: $(this).is(':selected')});
        });
        var selText = $sel.find('option:selected').text();
        var html = '<div class="webyaz-custom-sort" style="position:relative;display:inline-block;">';
        html += '<div class="webyaz-sort-btn" style="padding:10px 36px 10px 16px;border:2px solid #e0e0e0;border-radius:10px;background:#fff;cursor:pointer;font-family:Roboto,sans-serif;font-size:14px;font-weight:500;color:#333;white-space:nowrap;min-width:200px;position:relative;">';
        html += '<span class="webyaz-sort-text">' + selText + '</span>';
        html += '<svg style="position:absolute;right:12px;top:50%;transform:translateY(-50%);" width="12" height="12" viewBox="0 0 24 24" fill="#666"><path d="M7 10l5 5 5-5z"/></svg>';
        html += '</div><ul class="webyaz-sort-list" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:2px solid #e0e0e0;border-top:none;border-radius:0 0 10px 10px;list-style:none;margin:0;padding:0;z-index:9999;min-width:200px;box-shadow:0 8px 24px rgba(0,0,0,0.1);">';
        for (var i = 0; i < opts.length; i++) {
            html += '<li data-val="' + opts[i].val + '" style="padding:10px 16px;cursor:pointer;font-family:Roboto,sans-serif;font-size:13px;color:#444;transition:background 0.15s;' + (opts[i].selected ? 'font-weight:700;color:#446084;' : '') + '">' + opts[i].text + '</li>';
        }
        html += '</ul></div>';
        $sel.hide();
        $wrap.css('overflow','visible').append(html);
        $wrap.on('click', '.webyaz-sort-btn', function() {
            $(this).next('.webyaz-sort-list').toggle();
        });
        $wrap.on('click', '.webyaz-sort-list li', function() {
            var v = $(this).data('val');
            $sel.val(v).trigger('change');
            $wrap.find('.webyaz-sort-text').text($(this).text());
            $(this).parent().hide();
        });
        $wrap.on('mouseleave', '.webyaz-custom-sort', function() {
            $(this).find('.webyaz-sort-list').hide();
        });
        $wrap.find('.webyaz-sort-list li').hover(function() {
            $(this).css('background', '#f5f7fa');
        }, function() {
            $(this).css('background', '#fff');
        });
    });

})(jQuery);
