<?php

/**
 * Class Mentor_iuWare_Import_Tools
 */
class Mentor_iuWare_Import_Tools{

	function __construct() {

		add_action( 'admin_menu', array( &$this, 'admin_menu' ) );
		add_action( 'wp_ajax_iuware_import', array( &$this, 'iuware_import' ) );

	}

	function admin_menu(){

		add_management_page( 'iuWare Import', 'iuWare Import', 'administrator', 'mentor_iuware_import_tools', array( &$this, 'tools' ) );

	}

	function tools(){

        $saved = "";

        if( isset( $_REQUEST['save'] ) && wp_verify_nonce( $_REQUEST['nonce'], 'iuware_settings' ) ){

            update_option( 'iuware_running', isset($_REQUEST['running']) ? 1 : 0 );
            update_option( 'iuware_ssoid', (int)$_REQUEST['ssoid'] );
            update_option( 'iuware_update', isset($_REQUEST['update']) ? 1 : 0 );
            $saved = "Inställningarna är uppdaterade " . date( "Y-m-d H:i:s" ) . ".";

        }

        $iuware_running = (int)get_option( 'iuware_running' );
        $iuware_ssoid = (int)get_option( 'iuware_ssoid' );
        $iuware_update = (int)get_option( 'iuware_update' );

		?>

		<div class="wrap">

			<div id="icon-tools" class="icon32"><br></div><h2>iuWare Import</h2>
			<p>iuWare Import hämtar innehållet från detaljsidor i iuWare-sajterna hos Mentoronline.</p>
            <p>Importen startar från det SSOID du sätter. Varje tidning får sin egen kategori i denna WordPressinstallation.</p>
            <p>Importen går på cronjobb men detta gränssnitt kan också köras manuellt.</p>

            <?php

            if( !empty( $saved ) ){
                echo '<div class="updated">' . $saved . '</div>';
            }

            ?>

            <form method="post">

                <table class="widefat" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Fältnamn</th>
                            <th>Värde</th>
                            <th>Beskrivning</th>
                        </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td>Körs redan?</td>
                        <td>
                            <input name="running" type="checkbox"<?php checked( 1, $iuware_running ); ?> />
                        </td>
                        <td>
                            (Om importen redan körs kommer den att stoppas om denna kryssas ur och sparas)
                        </td>
                    </tr>
                    <tr>
                        <td>SSOID Start</td>
                        <td>
                            <input name="ssoid" type="text" value="<?php echo $iuware_ssoid; ?>" />
                        </td>
                        <td>
                            (denna uppdateras efter hand och kan återställas)
                        </td>
                    </tr>
                    <tr>
                        <td>Uppdatera</td>
                        <td>
                            <input name="update" type="checkbox"<?php checked( 1, $iuware_update ); ?> />
                        </td>
                        <td>
                            (Om importen ska skriva över befintlig importerad post)
                        </td>
                    </tr>
                    </tbody>
                    <tfoot>
                    <tr>
                        <th>Fältnamn</th>
                        <th>Värde</th>
                        <th>Beskrivning</th>
                    </tr>
                    </tfoot>
                </table>

                <br/>

                <input type="submit" name="save" value="Spara" class="button-primary" />
                <input type="hidden" name="nonce" value="<?php echo wp_create_nonce( 'iuware_settings' ); ?>" />

            </form>

            <br/><br/>

            <a href="<?php echo admin_url('admin-ajax.php'); ?>?action=iuware_import" target="_blank">Kör import i annat fönster</a>

		</div>

		<?php

	}

	function iuware_import(){

        $iuware_running = (int)get_option( 'iuware_running' );
        $iuware_ssoid = (int)get_option( 'iuware_ssoid' );
        $iuware_update = (int)get_option( 'iuware_update' );

        if( $iuware_running ) return;

        update_option( 'iuware_running', 1 );

        // first create root term...
        $term_iuWare = term_exists('iuWare', 'category');
        $term_iuWare = $term_iuWare['term_id'];
        if ( !$term_iuWare ) {
            wp_insert_term(
                'iuWare',
                'category');
            $term_iuWare = term_exists('iuWare', 'category');
            $term_iuWare = $term_iuWare['term_id'];
        }

        $step = 50;
        $pageid = 3868;
        $url = "http://www.plastnet.se/iuware.aspx";

        for( $ssoid = $iuware_ssoid; $ssoid<$iuware_ssoid+$step; $ssoid++ ){

            if( !(int)get_option( 'iuware_running' ) ) break;

            $date = "1999-01-01";
            $post_is_saved = false;

            $the_body = wp_remote_retrieve_body( wp_remote_get( $url . "?pageid=" . $pageid . "&ssoid=" . $ssoid ) );

            $matches = array();
            preg_match_all('/<div\s*class="container_centermain_article">(.*)<div>/s', $the_body, $matches);

            if( isset( $matches[1][0] ) ){

                $content = $matches[1][0];

                preg_match_all('/<p\s*class="paper">\((.*)\)<\/p>/', $content, $matches);
                $paper = isset($matches[1][0]) ? $this->decode( $matches[1][0] ) : '';

                preg_match_all('/<h1>(.*)<\/h1>/', $content, $matches);
                $headline = $this->decode( $matches[1][0] );

                preg_match_all('/<p\s*class="date">(.*)<\/p>/', $content, $matches);
                $date = $this->decode( $matches[1][0] );
                $date = date( 'Y-m-d', strtotime( $date ) );

                preg_match_all('/<p\s*class="preamble">(.*)<\/p>/', $content, $matches);
                $preamble = $this->decode( $matches[1][0] );

                preg_match_all('/<p\s*class="body">(.*)<\/p>/', $content, $matches);
                $body = $this->decode( $matches[1][0] );

                if( $headline == $preamble && $preamble == $body ){
                    echo "<p>" . $ssoid . ". No article</p>";
                }
                else if( $date < '1999-02-01' ){
                    echo "<p>" . $ssoid . ". No date in article</p>";
                }
                else if( empty( $headline ) ){
                    echo "<p>" . $ssoid . ". No headline in article</p>";
                }
                else{

                    $post_is_saved = true;

                    $term_paper = term_exists( $paper, 'category', $term_iuWare );
                    $term_paper = $term_paper['term_id'];
                    if ( !$term_paper ) {
                        wp_insert_term(
                            $paper,
                            'category',
                            array(
                                'parent' => $term_iuWare
                            )
                        );
                        $term_paper = term_exists( $paper, 'category' );
                        $term_paper = $term_paper['term_id'];
                    }

                    $args = array(
                        'meta_key'        => 'SSOID',
                        'meta_value'      => $ssoid,
                    );
                    $posts = get_posts( $args );

                    if( sizeof( $posts ) ){

                        if( !$iuware_update ){
                            echo "<p>" . $ssoid . ". No update allowed, " . $headline . "</p>";
                        }
                        else{
                            $post = array(
                                'ID' => $posts[0]->ID,
                                'post_content'   => $body,
                                'post_date'      => $date,
                                'post_excerpt'   => '',
                                'post_status'    => 'publish',
                                'post_title'     => $headline,
                                'post_type'      => 'post',
                                'post_author'	=> 1
                            );

                            $post_id = wp_update_post( $post );

                            update_post_meta( $post_id, 'IUWARE_SOURCE', $paper );
                            update_post_meta( $post_id, 'SSOID', $ssoid );

                            wp_set_post_terms( $post_id, array($term_iuWare, $term_paper), 'category' );

                            echo "<p>" . $ssoid . ". Updated, " . $headline . "</p>";

                        }

                    }
                    else{
                        $post = array(
                            'post_content'   => $body,
                            'post_date'      => $date,
                            'post_excerpt'   => '',
                            'post_status'    => 'publish',
                            'post_title'     => $headline,
                            'post_type'      => 'post',
                            'post_author'	=> 1
                        );

                        $post_id = wp_insert_post( $post );

                        update_post_meta( $post_id, 'IUWARE_SOURCE', $paper );
                        update_post_meta( $post_id, 'SSOID', $ssoid );

                        wp_set_post_terms( $post_id, array($term_iuWare, $term_paper), 'category' );

                        echo "<p>" . $ssoid . ". Inserted, " . $headline . "</p>";

                    }

                }
            }

            if( !$post_is_saved ){
                if( strtotime( $date ) < strtotime( '-1 week' ) ){
                    update_option( 'iuware_ssoid', $ssoid );
                }
            }
            else{
                update_option( 'iuware_ssoid', $ssoid );
            }

        }

        update_option( 'iuware_running', 0 );

        wp_die( 'iuWare Import har kört klart.', 'iuWare Import' );

	}

	function decode( $input ){

		$result = $input;

		$result = str_replace( '&nbsp;', ' ', $result );
		$result = str_replace( '&#229;', 'å', $result );
		$result = str_replace( '&#228;', 'ä', $result );
		$result = str_replace( '&#246;', 'ö', $result );

		$result = str_replace( '&#196;', 'Ä', $result );
		$result = str_replace( '&#197;', 'Å', $result );
		$result = str_replace( '&#214;', 'Ö', $result );

		return $result;

	}


}

new Mentor_iuWare_Import_Tools();

?>