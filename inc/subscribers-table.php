<?php

if(!class_exists('WP_List_Table')){
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class Incsub_Subscribe_By_Email_Subscribers_Table extends WP_List_Table {

	function __construct(){
        global $status, $page;
                
        parent::__construct( array(
            'singular'  => 'subscriptor', 
            'plural'    => 'subscriptors',
            'ajax'      => false
        ) );
        
    }

    function column_cb($item){
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            	'subscriptor',  
            	$item['subscription_ID']
        );
    }

    function column_email( $item ) {

    	$actions = array(
            'cancel'    => sprintf( __( '<span class="trash"><a class="trash" href="%s">%s</a></span>', INCSUB_SBE_LANG_DOMAIN ), 
            	esc_url( add_query_arg( array( 'action' => 'cancel', 'sid' => absint( $item['subscription_ID'] ) ) ) ),
            	__( 'Cancel subscription', INCSUB_SBE_LANG_DOMAIN )
            )
        );

        if ( $item['confirmation_flag'] == 0 ) {
            $actions['send_confirmation'] = sprintf( __( '<span><a class="trash" href="%s">%s</a></span>', INCSUB_SBE_LANG_DOMAIN ), 
                esc_url( add_query_arg( array( 'action' => 'send_confirmation', 'sid' => absint( $item['subscription_ID'] ) ) ) ),
                __( 'Resend confirmation mail', INCSUB_SBE_LANG_DOMAIN )
            );
        }
        
        return $item['subscription_email'] . $this->row_actions( $actions );
    }

    function column_created( $item ) { 
        return date_i18n( get_option( 'date_format' ), (int)$item['subscription_created'] );
    }

    function column_note( $item ) {
        return Incsub_Subscribe_By_Email::$confirmation_flag[ $item['confirmation_flag'] ];
    }

    function column_subscription_type( $item ) {
        return $item['subscription_note'];
    }

    function get_columns(){
        $columns = array(
            'cb'                    => '<input type="checkbox" />', //Render a checkbox instead of text
            'email'                 => __( 'Email', INCSUB_SBE_LANG_DOMAIN ),
            'created'               => __( 'Created', INCSUB_SBE_LANG_DOMAIN ),
            'note'                  => __( 'Note', INCSUB_SBE_LANG_DOMAIN ),
            'subscription_type'  	=> __( 'Subscription Type', INCSUB_SBE_LANG_DOMAIN )
        );
        return $columns;
    }

    function get_bulk_actions() {
        $actions = array(
            'cancel'    => __( 'Cancel subscriptions', INCSUB_SBE_LANG_DOMAIN )
        );
        return $actions;
    }

    function process_bulk_action() {
        
        if( 'cancel' === $this->current_action() ) {

            $model = Incsub_Subscribe_By_Email_Model::get_instance();
        	if ( ! isset( $_POST['subscriptor'] ) && isset( $_GET['sid'] ) ) {
                $subscriber = $model->get_subscriber( absint( $_GET['sid'] ) );

                if ( $subscriber )
        			$model->cancel_subscription( $subscriber->user_key );
        	}
        	else {
        		$subscriptions = $_POST['subscriptor'];
        		if ( ! empty( $subscriptions ) ) {
        			foreach ( $subscriptions as $subscription ) {
                        $subscriber = $model->get_subscriber( $subscription );
                        if ( $subscriber )
                        	$model->cancel_subscription( $subscriber->user_key );
					}
        		}
        	}

        	?>
				<div class="updated">
					<p><?php _e( 'Subscription(s) deleted', INCSUB_SBE_LANG_DOMAIN ); ?></p>
				</div>
        	<?php
        }

        if ( 'send_confirmation' == $this->current_action() && isset( $_GET['sid'] ) ) {
            Incsub_Subscribe_By_Email::send_confirmation_mail( absint( $_GET['sid'] ) );
            ?>
                <div class="updated">
                    <p><?php _e( 'Confirmation mail sent', INCSUB_SBE_LANG_DOMAIN ); ?></p>
                </div>
            <?php
        }

        
    }

    function get_sortable_columns() {
    	$sortable_columns = array(
            'email'     => array( 'subscription_email', isset( $_GET['orderby'] ) && isset( $_GET['order'] ) && 'subscription_email' == $_GET['orderby'] ? $_GET['order'] : false ),
            'created'   => array( 'subscription_created', isset( $_GET['orderby'] ) && isset( $_GET['order'] ) && 'subscription_created' == $_GET['orderby'] ? $_GET['order'] : false ),
            'subscription_type'   => array( 'subscription_note', isset( $_GET['orderby'] ) && isset( $_GET['order'] ) && 'subscription_note' == $_GET['orderby'] ? $_GET['order'] : false ),
        );
        return $sortable_columns;
    }

    function prepare_items() {
        global $wpdb, $page;

        $per_page = 15;
      
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = array($columns, $hidden, $sortable);

        $this->process_bulk_action();
        $current_page = $this->get_pagenum();

        $model = Incsub_Subscribe_By_Email_Model::get_instance();

        $search = false;
        if ( isset( $_POST['s'] ) )
            $search = $_POST['s'];

        $subscribers = $model->get_subscribers( $current_page, $per_page, $sortable, $search );

        $this->items = $subscribers['subscribers'];               

        $this->set_pagination_args( array(
            'total_items' => $subscribers['total'],                 
            'per_page'    => $per_page,              
            'total_pages' => ceil($subscribers['total']/$per_page)  
        ) );
    }
}