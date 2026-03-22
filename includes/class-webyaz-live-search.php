<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Live_Search {

    public function __construct() {
        add_action('wp_footer', array($this, 'render_search'));
        add_action('wp_ajax_webyaz_live_search', array($this, 'ajax_search'));
        add_action('wp_ajax_nopriv_webyaz_live_search', array($this, 'ajax_search'));
    }

    public function ajax_search() {
        $q = sanitize_text_field($_POST['q'] ?? '');
        if (strlen($q) < 2) { wp_send_json_success(array('products' => array())); return; }

        $args = array(
            'post_type' => 'product',
            'posts_per_page' => 8,
            's' => $q,
            'post_status' => 'publish',
        );
        $query = new WP_Query($args);
        $products = array();

        while ($query->have_posts()) {
            $query->the_post();
            $product = wc_get_product(get_the_ID());
            if (!$product) continue;
            $products[] = array(
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'price' => $product->get_price_html(),
                'image' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') ?: wc_placeholder_img_src('thumbnail'),
                'url' => $product->get_permalink(),
                'category' => strip_tags(wc_get_product_category_list($product->get_id())),
            );
        }
        wp_reset_postdata();
        wp_send_json_success(array('products' => $products));
    }

    public function render_search() {
        if (is_admin()) return;
        ?>
        <div id="webyazLiveSearch" class="webyaz-ls-overlay" style="display:none;" onclick="if(event.target===this)this.style.display='none';">
            <div class="webyaz-ls-box">
                <div class="webyaz-ls-input-wrap">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="#999"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
                    <input type="text" id="webyazLsInput" placeholder="Urun ara..." autocomplete="off">
                    <button onclick="document.getElementById('webyazLiveSearch').style.display='none';" class="webyaz-ls-close">&times;</button>
                </div>
                <div id="webyazLsResults" class="webyaz-ls-results"></div>
            </div>
        </div>
        <script>
        (function(){
            var timer;
            var input=document.getElementById('webyazLsInput');
            if(!input) return;
            input.addEventListener('input',function(){
                clearTimeout(timer);
                var q=this.value.trim();
                var res=document.getElementById('webyazLsResults');
                if(q.length<2){res.innerHTML='';return;}
                res.innerHTML='<div style="text-align:center;padding:20px;color:#999;">Araniyor...</div>';
                timer=setTimeout(function(){
                    var fd=new FormData();
                    fd.append('action','webyaz_live_search');
                    fd.append('q',q);
                    fetch(webyaz_ajax.ajax_url,{method:'POST',body:fd})
                    .then(function(r){return r.json();})
                    .then(function(d){
                        var items=d.data.products;
                        if(!items.length){res.innerHTML='<div style="text-align:center;padding:20px;color:#999;">Sonuc bulunamadi</div>';return;}
                        var html='';
                        items.forEach(function(p){
                            html+='<a href="'+p.url+'" class="webyaz-ls-item">';
                            html+='<img src="'+p.image+'" alt="">';
                            html+='<div class="webyaz-ls-info"><div class="webyaz-ls-name">'+p.name+'</div>';
                            if(p.category) html+='<div class="webyaz-ls-cat">'+p.category+'</div>';
                            html+='<div class="webyaz-ls-price">'+p.price+'</div></div></a>';
                        });
                        res.innerHTML=html;
                    });
                },300);
            });

            document.querySelectorAll('.header-search-form input[type="search"], .search-field, .searchform input[type="text"]').forEach(function(el){
                el.addEventListener('focus',function(e){
                    e.preventDefault();
                    document.getElementById('webyazLiveSearch').style.display='flex';
                    setTimeout(function(){input.focus();},100);
                });
            });

            document.addEventListener('keydown',function(e){
                if((e.ctrlKey||e.metaKey)&&e.key==='k'){
                    e.preventDefault();
                    document.getElementById('webyazLiveSearch').style.display='flex';
                    setTimeout(function(){input.focus();},100);
                }
                if(e.key==='Escape') document.getElementById('webyazLiveSearch').style.display='none';
            });
        })();
        </script>
        <?php
    }
}

new Webyaz_Live_Search();
