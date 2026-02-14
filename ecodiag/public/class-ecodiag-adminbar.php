<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Module 1 — Front-end admin bar diagnostic.
 * Injects EcoDiag indicators into the WordPress admin bar.
 */
class EcoDiag_Adminbar {

    public function __construct() {
        add_action( 'admin_bar_menu', array( $this, 'add_menu' ), 100 );
        add_action( 'wp_head', array( $this, 'inline_styles' ), 999 );
        add_action( 'wp_footer', array( $this, 'inline_script' ), 999 );
    }

    /**
     * Check if current user should see the bar.
     */
    private function can_view() {
        if ( ! is_user_logged_in() || is_admin() ) {
            return false;
        }
        if ( ! is_admin_bar_showing() ) {
            return false;
        }
        $roles = get_option( 'ecodiag_allowed_roles', array( 'administrator' ) );
        $user  = wp_get_current_user();
        return array_intersect( $roles, $user->roles ) ? true : false;
    }

    /**
     * Add the EcoDiag node to the admin bar.
     */
    public function add_menu( $wp_admin_bar ) {
        if ( ! $this->can_view() ) {
            return;
        }

        // Main node
        $wp_admin_bar->add_node( array(
            'id'    => 'ecodiag',
            'title' => '<span id="ecodiag-bar-score" class="ecodiag-bar-item">EcoDiag <span class="ecodiag-badge ecodiag-loading">…</span></span>',
            'href'  => '#',
            'meta'  => array( 'class' => 'ecodiag-bar-root' ),
        ) );

        // Sub-items (populated via JS)
        $indicators = array(
            'weight'   => __( 'Poids total', 'ecodiag' ),
            'requests' => __( 'Requêtes HTTP', 'ecodiag' ),
            'dom'      => __( 'Taille DOM', 'ecodiag' ),
            'js'       => __( 'Scripts JS', 'ecodiag' ),
            'css'      => __( 'Feuilles CSS', 'ecodiag' ),
            'img'      => __( 'Images non optimisées', 'ecodiag' ),
        );

        foreach ( $indicators as $key => $label ) {
            $wp_admin_bar->add_node( array(
                'id'     => 'ecodiag-' . $key,
                'parent' => 'ecodiag',
                'title'  => '<span class="ecodiag-indicator" id="ecodiag-ind-' . $key . '">' . esc_html( $label ) . ': <span class="ecodiag-val">…</span></span>',
            ) );
        }

        // Link to admin
        $wp_admin_bar->add_node( array(
            'id'     => 'ecodiag-admin',
            'parent' => 'ecodiag',
            'title'  => __( '→ Voir le diagnostic complet', 'ecodiag' ),
            'href'   => admin_url( 'admin.php?page=ecodiag' ),
        ) );

        // Refresh button
        $wp_admin_bar->add_node( array(
            'id'     => 'ecodiag-refresh',
            'parent' => 'ecodiag',
            'title'  => '<span id="ecodiag-refresh-btn">' . __( '↻ Relancer le diagnostic', 'ecodiag' ) . '</span>',
            'href'   => '#',
        ) );
    }

    /**
     * Inline CSS for admin bar (<5 Ko constraint).
     */
    public function inline_styles() {
        if ( ! $this->can_view() ) return;
        ?>
        <style id="ecodiag-adminbar-css">
        #wpadminbar .ecodiag-bar-root .ab-item{display:flex;align-items:center;gap:6px}
        .ecodiag-badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;line-height:1.4;color:#fff}
        .ecodiag-loading{background:#888}
        .ecodiag-green{background:#2ecc71}
        .ecodiag-orange{background:#f39c12}
        .ecodiag-red{background:#e74c3c}
        #wpadminbar .ecodiag-indicator{display:flex;align-items:center;gap:4px;font-size:12px}
        #wpadminbar .ecodiag-indicator .ecodiag-dot{display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:4px}
        #wpadminbar #ecodiag-refresh-btn{cursor:pointer;color:#0073aa}
        #wpadminbar #ecodiag-refresh-btn:hover{text-decoration:underline}
        #wpadminbar .ecodiag-tooltip{position:relative}
        #wpadminbar .ecodiag-tooltip:hover::after{content:attr(data-tip);position:absolute;bottom:100%;left:50%;transform:translateX(-50%);background:#23282d;color:#fff;padding:4px 8px;border-radius:3px;font-size:11px;white-space:nowrap;z-index:99999}
        </style>
        <?php
    }

    /**
     * Inline JS for admin bar (<5 Ko constraint).
     */
    public function inline_script() {
        if ( ! $this->can_view() ) return;
        $nonce    = wp_create_nonce( 'ecodiag_nonce' );
        $ajax_url = admin_url( 'admin-ajax.php' );
        $edit_base = admin_url( 'post.php?action=edit&post=' );
        ?>
        <script id="ecodiag-adminbar-js">
        (function(){
            var ajaxUrl='<?php echo esc_js( $ajax_url ); ?>';
            var nonce='<?php echo esc_js( $nonce ); ?>';
            var editBase='<?php echo esc_js( $edit_base ); ?>';
            var thresholds={
                weight:[512000,1048576],requests:[25,50],dom:[800,1500],
                js:[5,10],css:[3,6],img:[0,3]
            };

            function color(key,val){
                var t=thresholds[key];
                if(val<=t[0])return'green';
                if(val<=t[1])return'orange';
                return'red';
            }

            function fmt(bytes){
                if(bytes>=1048576)return(bytes/1048576).toFixed(2)+' Mo';
                if(bytes>=1024)return(bytes/1024).toFixed(1)+' Ko';
                return bytes+' o';
            }

            function setVal(key,val,raw){
                var el=document.querySelector('#ecodiag-ind-'+key+' .ecodiag-val');
                if(el){
                    el.textContent=val;
                    var c=color(key,raw);
                    el.innerHTML='<span class="ecodiag-dot" style="background:'+
                        (c==='green'?'#2ecc71':c==='orange'?'#f39c12':'#e74c3c')+'"></span>'+val;
                }
            }

            function loadData(force){
                var badge=document.querySelector('#ecodiag-bar-score .ecodiag-badge');
                if(badge){badge.textContent='…';badge.className='ecodiag-badge ecodiag-loading';}

                var fd=new FormData();
                fd.append('action','ecodiag_adminbar_data');
                fd.append('nonce',nonce);
                fd.append('url',window.location.href);
                if(force)fd.append('force','1');

                fetch(ajaxUrl,{method:'POST',body:fd,credentials:'same-origin'})
                .then(function(r){return r.json()})
                .then(function(r){
                    if(!r.success)return;
                    var d=r.data;

                    // Score badge
                    if(badge){
                        badge.textContent=d.score+'/100';
                        badge.className='ecodiag-badge ecodiag-'+
                            (d.score>=75?'green':d.score>=50?'orange':'red');
                    }

                    // Link to edit page
                    if(d.post_id){
                        var root=document.querySelector('#wp-admin-bar-ecodiag > a');
                        if(root)root.href=editBase+d.post_id+'#ecodiag-metabox';
                    }

                    setVal('weight',fmt(d.total_weight),d.total_weight);
                    setVal('requests',d.requests_count,d.requests_count);
                    setVal('dom',d.dom_size,d.dom_size);
                    setVal('js',d.js_count,d.js_count);
                    setVal('css',d.css_count,d.css_count);
                    setVal('img',d.img_issues_count,d.img_issues_count);
                })
                .catch(function(){
                    if(badge){badge.textContent='Err';badge.className='ecodiag-badge ecodiag-red';}
                });
            }

            document.addEventListener('DOMContentLoaded',function(){
                loadData(false);
                var ref=document.getElementById('ecodiag-refresh-btn');
                if(ref){
                    ref.addEventListener('click',function(e){
                        e.preventDefault();
                        loadData(true);
                    });
                }
            });
        })();
        </script>
        <?php
    }
}
