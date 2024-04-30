/**
 * Custom shortcode to display WPForms form entries in table view.
 *
 * Basic usage: [wpforms_entries_table id="FORMID"].
 * 
 * Possible shortcode attributes:
 * id (required)  Form ID of which to show entries.
 * user           User ID, or "current" to default to current logged in user.
 * fields         Comma separated list of form field IDs.
 * number         Number of entries to show, defaults to 30.
 * 
 * @link https://wpforms.com/developers/how-to-display-form-entries/
 *
 * Realtime counts could be delayed due to any caching setup on the site
 *
 * @param array $atts Shortcode attributes.
 * 
 * @return string
 */
  
function wpf_entries_table( $atts ) {
     $post_id = get_the_ID();
    // Pull ID shortcode attributes.
    $atts = shortcode_atts(
        [
            'id'     => '',
            'user'   => '',
            'fields' => '',
            'number' => '',
            'type'   => 'all', // all, unread, read, or starred.
            'sort'   => '',
            'order'  => 'asc',
        ],
        $atts
    );
  
    // Check for an ID attribute (required) and that WPForms is in fact
    // installed and activated.
    if ( empty( $atts[ 'id' ] ) || ! function_exists( 'wpforms' ) ) {
        return;
    }
  
    // Get the form, from the ID provided in the shortcode.
    $form = wpforms()->form->get( absint( $atts[ 'id' ] ) );
  
    // If the form doesn't exists, abort.
    if ( empty( $form ) ) {
        return;
    }
  
    // Pull and format the form data out of the form object.
    $form_data = ! empty( $form->post_content ) ? wpforms_decode( $form->post_content ) : '';
  
    // Check to see if we are showing all allowed fields, or only specific ones.
    $form_field_ids = isset( $atts[ 'fields' ] ) && $atts[ 'fields' ] !== '' ? explode( ',', str_replace( ' ', '', $atts[ 'fields' ] ) ) : [];
  
    // Setup the form fields.
    if ( empty( $form_field_ids ) ) {
        $form_fields = $form_data[ 'fields' ];
    } else {
        $form_fields = [];
        foreach ( $form_field_ids as $field_id ) {
            if ( isset( $form_data[ 'fields' ][ $field_id ] ) ) {
                $form_fields[ $field_id ] = $form_data[ 'fields' ][ $field_id ];
            }
        }
    }
  
    if ( empty( $form_fields ) ) {
        return;
    }
  
    // Here we define what the types of form fields we do NOT want to include,
    // instead they should be ignored entirely.
    $form_fields_disallow = apply_filters( 'wpforms_frontend_entries_table_disallow', [ 'divider', 'html', 'pagebreak', 'captcha' ] );
  
    // Loop through all form fields and remove any field types not allowed.
    foreach ( $form_fields as $field_id => $form_field ) {
        if ( in_array( $form_field[ 'type' ], $form_fields_disallow, true ) ) {
            unset( $form_fields[ $field_id ] );
        }
    }
  
    $entries_args = [
        'form_id' => absint( $atts[ 'id' ] ),
    ];
  
    // Narrow entries by user if user_id shortcode attribute was used.
    if ( ! empty( $atts[ 'user' ] ) ) {
        if ( $atts[ 'user' ] === 'current' && is_user_logged_in() ) {
            $entries_args[ 'user_id' ] = get_current_user_id();
        } else {
            $entries_args[ 'user_id' ] = absint( $atts[ 'user' ] );
        }
    }
  
    // Number of entries to show. If empty, defaults to 30.
    if ( ! empty( $atts[ 'number' ] ) ) {
        $entries_args[ 'number' ] = absint( $atts[ 'number' ] );
    }
  
    // Filter the type of entries all, unread, read, or starred
    if ( $atts[ 'type' ] === 'unread' ) {
        $entries_args[ 'viewed' ] = '0';
    } elseif( $atts[ 'type' ] === 'read' ) {
        $entries_args[ 'viewed' ] = '1';
    } elseif ( $atts[ 'type' ] === 'starred' ) {
        $entries_args[ 'starred' ] = '1';
    }
    
    $entries_args['field_id' ]= 'postid';
    $entries_args['value' ]= $post_id;
    $entries_args['value_compare']='is';
     
  
    // Get all entries for the form, according to arguments defined.
    // There are many options available to query entries. To see more, check out
    // the get_entries() function inside class-entry.php (https://a.cl.ly/bLuGnkGx).
    $entries = json_decode(json_encode(wpforms()->entry->get_entries( $entries_args )), true);
  
    if ( empty( $entries ) ) {
        return '<p>No entries found.</p>';
    }
     
    foreach($entries as $key => $entry) {
        $entries[$key][ 'fields' ] = json_decode($entry[ 'fields' ], true);
        $entries[$key][ 'meta' ] = json_decode($entry[ 'meta' ], true);
    }
     
    if ( !empty($atts[ 'sort' ]) && isset($entries[0][ 'fields' ][$atts[ 'sort' ]] ) ) {
        if ( strtolower($atts[ 'order' ]) == 'asc' ) {
            usort($entries, function ($entry1, $entry2) use ($atts) {
                return strcmp($entry1[ 'fields' ][$atts[ 'sort' ]][ 'value' ], $entry2[ 'fields' ][$atts[ 'sort' ]][ 'value' ]);
            });         
        } elseif ( strtolower($atts[ 'order' ]) == 'desc' ) {
            usort($entries, function ($entry1, $entry2) use ($atts) {
                return strcmp($entry2[ 'fields' ][$atts[ 'sort' ]][ 'value' ], $entry1[ 'fields' ][$atts[ 'sort' ]]['value']);
            });
        }
    }
     
    ob_start();
  
    echo '<table class="wpforms-frontend-entries">';
  
        echo '<thead><tr>';
  
            // Loop through the form data so we can output form field names in
            // the table header.
            foreach ( $form_fields as $form_field ) {
  
                // Output the form field name/label.
                echo '<th>';
                    echo esc_html( sanitize_text_field( 'Absents'));
                echo '</th>';
            }
  
        echo '</tr></thead>';
  
        echo '<tbody>';
  
            // Now, loop through all the form entries.
            foreach ( $entries as $entry ) {
  
                echo '<tr>';
  
                $entry_fields = $entry[ 'fields' ];
  
                foreach ( $form_fields as $form_field ) {
  
                    echo '<td>';
  
                        foreach ( $entry_fields as $entry_field ) {
                            if ( absint( $entry_field[ 'id' ] ) === absint( $form_field[ 'id' ] ) ) {
                                echo apply_filters( 'wpforms_html_field_value', wp_strip_all_tags( $entry_field[ 'value' ] ), $entry_field, $form_data, 'entry-frontend-table' );
                                break;
                            }
                        }
  
                    echo '</td>';
                }
  
                echo '</tr>';
            }
  
        echo '</tbody>';
  
    echo '</table>';
  
    $output = ob_get_clean();
  
    return $output;
}
add_shortcode( 'wpforms_entries_table', 'wpf_entries_table' );
