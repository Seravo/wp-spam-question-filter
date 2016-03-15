<?php
/**
 * Plugin Name: WP Spam Question Filter
 * Plugin URI: https://github.com/Seravo/wp-spam-question-filter
 * Description: This plugin fights comment and registration spam on your website. Original plugin at http://www.compdigitec.com/apps/wpnobot forked by Seravo
 * Version: 1.0
 * Author: Seravo Oy
 * Author URI: http://seravo.fi
 * License: 3-clause BSD
 * Text Domain: wp_spam_question_filter
*/

define('wp_spam_question_filter_VERSION', '1.0');
define('wp_spam_question_filter_current_db_version', 2); //database version

/*
 *      Redistribution and use in source and binary forms, with or without
 *      modification, are permitted provided that the following conditions are
 *      met:
 *
 *      * Redistributions of source code must retain the above copyright
 *        notice, this list of conditions and the following disclaimer.
 *      * Redistributions in binary form must reproduce the above
 *        copyright notice, this list of conditions and the following disclaimer
 *        in the documentation and/or other materials provided with the
 *        distribution.
 *      * Neither the name of the Compdigitec nor the names of its
 *        contributors may be used to endorse or promote products derived from
 *        this software without specific prior written permission.
 *
 *      THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 *      "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 *      LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 *      A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 *      OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 *      SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 *      LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 *      DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 *      THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 *      (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 *      OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */


register_activation_hook( __FILE__, 'wp_spam_question_filter_activate' );
function wp_spam_question_filter_activate() {

	if(get_option('wp_spam_question_filter_db_version') === false) add_option('wp_spam_question_filter_db_version', wp_spam_question_filter_current_db_version);
	if(get_option('wp_spam_question_filter_enable') === false) add_option('wp_spam_question_filter_enable', true);
	if(get_option('wp_spam_question_filter_questions') === false) add_option('wp_spam_question_filter_questions', array('What is the sum of 2 and 7?'));
	if(get_option('wp_spam_question_filter_answers') === false) add_option('wp_spam_question_filter_answers', array(array('nine', '9')));
	if(get_option('wp_spam_question_filter_registration') === false) add_option('wp_spam_question_filter_registration', false);
	if(get_option('wp_spam_question_filter_prefix') === false) add_option('wp_spam_question_filter_prefix', 'Spam-test:');

}

register_deactivation_hook( __FILE__, 'wp_spam_question_filter_deactivate' );
function wp_spam_question_filter_deactivate() {
	/* Stub */
}

register_uninstall_hook( __FILE__, 'wp_spam_question_filter_remove' );
function wp_spam_question_filter_remove() {

	delete_option('wp_spam_question_filter_db_version');
	delete_option('wp_spam_question_filter_enable');
	delete_option('wp_spam_question_filter_questions');
	delete_option('wp_spam_question_filter_answers');
	delete_option('wp_spam_question_filter_registration');

}

add_action('init', 'wp_spam_question_filter_init');
function wp_spam_question_filter_init() {

	load_plugin_textdomain( 'wp_spam_question_filter', false, dirname( plugin_basename( __FILE__ ) ) );
	wp_enqueue_script('jquery');

}

add_action('admin_menu', 'wp_spam_question_filter_admin_init');
function wp_spam_question_filter_admin_init() {

	add_submenu_page( 'options-general.php', 'WP Spam Question Filter &rarr; Edit Question', 'Spam Question Filter', 'moderate_comments', 'wp_spam_question_filter_page', 'wp_spam_question_filter_admin' );

	if(get_option('wp_spam_question_filter_question') !== false) { /* database version 1 */
		// we now support multiple questions
		add_option('wp_spam_question_filter_questions', array(strval(get_option('wp_spam_question_filter_question'))));
		delete_option('wp_spam_question_filter_question');
		update_option('wp_spam_question_filter_answers', array(get_option('wp_spam_question_filter_answers')));
		add_option('wp_spam_question_filter_db_version', wp_spam_question_filter_current_db_version);
	}
}

add_action('comment_form_after_fields', 'wp_spam_question_filter_comment_field');
add_action('comment_form_logged_in_after', 'wp_spam_question_filter_comment_field');
function wp_spam_question_filter_comment_field() {
	wp_spam_question_filter_field('comment');
}


add_action('register_form', 'wp_spam_question_filter_registration_field');
function wp_spam_question_filter_registration_field() {
	wp_spam_question_filter_field('registration');
}



/*
* This displays the question form
*/

function wp_spam_question_filter_field($context = 'comment') {

	if( current_user_can('editor') || current_user_can('administrator') || !wp_spam_question_filter_get_option('enable') || ( $context == 'registration' && !wp_spam_question_filter_get_option('registration'))) {
		return;
	}

	$questions = wp_spam_question_filter_get_option('questions');
	$answers = wp_spam_question_filter_get_option('answers');
	$selected_id = rand(0, count($questions) - 1);
?>

	<p class="comment-form-wp_spam_question_filter form-group">

		<label for="seravo_simple_answer"><?php echo wp_spam_question_filter_get_option('prefix'); echo ' ' . htmlspecialchars($questions[$selected_id]); ?>*</label>
		<input
			id="seravo_simple_answer"
			name="seravo_simple_answer"
			type="text"
			value=""
			size="30"
			class="form-control"
			<?php if($context == 'registration') { ?> tabindex="25" <?php }; ?>
		>
		<input type="hidden" name="seravo_simple_answer_question" value="<?php echo $selected_id; ?>" />
		<input type="hidden" name="seravo_simple_answer_question_hash" value="<?php echo wp_spam_question_filter_security_hash($selected_id, $questions[$selected_id], $answers[$selected_id]); ?>" />

	</p>

<?php
}

add_filter('preprocess_comment', 'wp_spam_question_filter_filter');
add_action('user_registration_email', 'wp_spam_question_filter_filter');
function wp_spam_question_filter_filter($x) {

	if( current_user_can('editor') || current_user_can('administrator') || ( !is_array($x) && !wp_spam_question_filter_get_option('registration') ) ||$x['comment_type'] == 'pingback' || $x['comment_type'] == 'trackback' || !wp_spam_question_filter_get_option('enable')) {
		return $x;
	}

	if(!array_key_exists('seravo_simple_answer', $_POST) || !array_key_exists('seravo_simple_answer_question', $_POST) || trim($_POST['seravo_simple_answer']) == '') {
		wp_die(__('Error: Please fill in the required question.', 'wp_spam_question_filter'));
	}

	$question_id = intval($_POST['seravo_simple_answer_question']);
	$questions_all = wp_spam_question_filter_get_option('questions');
	$answers_all = wp_spam_question_filter_get_option('answers');

	// Hash verification to make sure the bot isn't picking on one answer.
	// This does not mean that they got the question right.
	if(trim($_POST['seravo_simple_answer_question_hash']) != wp_spam_question_filter_security_hash($question_id, $questions_all[$question_id], $answers_all[$question_id])) {
		wp_die(__('Error: Please fill in the correct answer to the question.', 'wp_spam_question_filter'));
	}

	// Verify the answer.
	if($question_id < count($answers_all)) {
		$answers = $answers_all[$question_id];
		foreach($answers as $answer) {
			if(trim(strtolower($_POST['seravo_simple_answer'])) == strtolower($answer)) return $x;
		}
	}

	wp_die(__('Error: Please fill in the correct answer to the question.', 'wp_spam_question_filter'));
}

function wp_spam_question_filter_get_option($o) {
	switch($o) {
		case 'enable':
			return (bool) get_option('wp_spam_question_filter_enable');
			break;
		case 'questions':
			$tmp = get_option('wp_spam_question_filter_questions');
			if( $tmp === false ) return array();
			else return $tmp;
			break;
		case 'answers':
			$tmp = get_option('wp_spam_question_filter_answers');
			if( $tmp === false ) return array();
			else return $tmp;
			break;
		case 'registration':
			return (bool) get_option('wp_spam_question_filter_registration');
			break;
		case 'prefix':
			return get_option('wp_spam_question_filter_prefix');
			break;
		default:
			return null;
	}
}

/*
* Hash format: SHA256( Question ID + Question Title + serialize( Question Answers ) )
*/
function wp_spam_question_filter_security_hash($id, $question, $answer) {
	$hash_string = strval($id) . strval($question) . serialize($answer);
	return hash('sha256', $hash_string);
}

function wp_spam_question_filter_template($id_, $question, $answers) {

	$id = intval($id_);
?>

	<tr valign="top" class="wp_spam_question_filter_row_<?php echo $id; ?>">
		<th scope="row">
			<?php _e('Question to present to bot','wp_spam_question_filter'); ?>
		</th>
		<td>

			<input type="input" name="wp_spam_question_filter_question_<?php echo $id; ?>" size="70" value="<?php echo htmlspecialchars($question); ?>" placeholder="<?php _e('Type here to add a new question','wp_spam_question_filter'); ?>" /><a href="javascript:void(0)" onclick="wp_spam_question_filter_delete_entire_question(&quot;<?php echo $id ?>&quot;)"><?php echo __('Delete Question'); ?></a>

		</td>
	</tr>
	<tr valign="top" class="wp_spam_question_filter_row_<?php echo $id; ?>">
		<th scope="row">
			<?php _e('Possible Answers','wp_spam_question_filter'); ?>
		</th>
		<td>
			<?php
				$i = 0;
				foreach($answers as $value) {
					echo "<span id=\"wp_spam_question_filter_line_{$id}_$i\">";
					printf('<input type="input" id="wp_spam_question_filter_answer_%1$d_%2$d" name="wp_spam_question_filter_answers_%1$d[]" size="70" value="%3$s" />', $id, $i, htmlspecialchars($value));
					echo "<a href=\"javascript:void(0)\" onclick=\"wp_spam_question_filter_delete(&quot;$id&quot;, &quot;$i&quot;)\">" . __('Delete') . "</a>";
					echo "<br /></span>\n";
					$i++;
				}
				echo "<script id=\"wp_spam_question_filter_placeholder_$id\">ct[$id] = $i;</script>";
			?>
			<button onclick="return wp_spam_question_filter_add_newitem(<?php echo $id; ?>)"><?php _e('Add New','wp_spam_question_filter'); ?></button>
		</td>
	</tr>
<?php
}

/*
* Displays options-page
*/
function wp_spam_question_filter_admin() {

	if(!current_user_can('moderate_comments')) return;

	if(isset($_POST['submit'])) {
		$questions = array();
		$answers = array();

		foreach($_POST as $key => $value) {
			if(strpos($key, 'wp_spam_question_filter_question_') === 0) {
				// value starts with wp_spam_question_filter_question_
				$q_id = str_replace('wp_spam_question_filter_question_','',$key);

				if(trim(strval($value)) != '') { // if not empty
					$question_slashed = trim(strval($value));
					// WordPress seems to add quotes by default, see:
					// http://stackoverflow.com/questions/1746078/wordpress-2-8-6-foobars-my-theme-options-with-escape-slashes#answers-header
					// http://core.trac.wordpress.org/ticket/18322
					$questions[] = stripslashes($question_slashed);
					$answers_slashed = array_filter($_POST['wp_spam_question_filter_answers_' . $q_id]);

					foreach($answers_slashed as $key => $value) {
						$answers_slashed[$key] = stripslashes($value);
					}

					$answers[] = $answers_slashed;
				}
			}
		}

		update_option('wp_spam_question_filter_enable',(bool)$_POST['wp_spam_question_filter_enabled']);
		update_option('wp_spam_question_filter_prefix', $_POST['wp_spam_question_filter_prefix']);
		update_option('wp_spam_question_filter_questions', $questions);
		update_option('wp_spam_question_filter_answers', $answers);

		if(array_key_exists( 'wp_spam_question_filter_registration', $_POST )) {
			update_option('wp_spam_question_filter_registration', true);
		}

		else {
			update_option('wp_spam_question_filter_registration', false);
			add_settings_error('wp_spam_question_filter', 'wp_spam_question_filter_updated', __('WP Spam Question Filter settings updated.','wp_spam_question_filter'), 'updated');
		}
	}

	$wp_spam_question_filter_enabled = wp_spam_question_filter_get_option('enable');
	$wp_spam_question_filter_questions = wp_spam_question_filter_get_option('questions');
	$wp_spam_question_filter_answers = wp_spam_question_filter_get_option('answers');
	$wp_spam_question_filter_registration = wp_spam_question_filter_get_option('registration');

?>
<div class="wrap">
	<h2><?php _e('Edit WP Spam Question Filter', 'wp_spam_question_filter'); ?></h2>

	<?php settings_errors(); ?>
	<form method="post" name="wp_spam_question_filter_form">

		<?php settings_fields('discussion'); ?>
		<table class="form-table">
			<tr valign="top">
			<th scope="row"><?php _e('Enable WP Spam Question Filter', 'wp_spam_question_filter'); ?></th>
			<td>
				<fieldset>
					<input type="radio" name="wp_spam_question_filter_enabled" value="1" <?php if($wp_spam_question_filter_enabled) echo 'checked="checked"' ?> /> <?php _e('Yes'); ?>
					<input type="radio" name="wp_spam_question_filter_enabled" value="0" <?php if(!$wp_spam_question_filter_enabled) echo 'checked="checked"' ?> /> <?php _e('No'); ?>
				</fieldset>
			</td>
			</tr>
			<tr valign="top">
			<th scope="row"><?php _e('Protect the registration page too?', 'wp_spam_question_filter'); ?></th>
			<td>
				<fieldset>
					<input type="checkbox" name="wp_spam_question_filter_registration" value="1" <?php if($wp_spam_question_filter_registration) echo 'checked="checked"' ?> />
				</fieldset>
			</td>
			</tr>
			<tr valign="top">
			<th scope="row"><?php _e('Question prefix','wp_spam_question_filter'); ?></th>
			<td>
				<fieldset>
					<input type="input" name="wp_spam_question_filter_prefix" value="<?php echo wp_spam_question_filter_get_option('prefix'); ?>" />
				</fieldset>
			</td>
			</tr>
			<tr colspan="2">
			<td>
				<b><?php _e('Questions to present to bot','wp_spam_question_filter'); ?></b>
			</td>
			</tr>
			<script type="text/javascript">
			var ct = Array();
			function wp_spam_question_filter_delete(id, x) {
				jQuery("#wp_spam_question_filter_line_" + id + "_" + x).remove();
			}

			function wp_spam_question_filter_delete_entire_question(id) {
				jQuery("tr.wp_spam_question_filter_row_" + id).remove();
			}

			function wp_spam_question_filter_add_newitem(id) {
				jQuery("#wp_spam_question_filter_placeholder_" + id).before("<span id=\"wp_spam_question_filter_line_" + id + "_" + ct[id] + "\"><input type=\"input\" id=\"wp_spam_question_filter_answer_" + id + "_" + ct + "\" name=\"wp_spam_question_filter_answers_" + id + "[]\" size=\"70\" value=\"\" placeholder=\"<?php _e('Enter a new answer here','wp_spam_question_filter'); ?>\" /><a href=\"javascript:void(0)\" onclick=\"wp_spam_question_filter_delete(&quot;" + id + "&quot;, &quot;" + ct[id] + "&quot;)\"><?php echo __('Delete'); ?></a><br /></span>");
				ct[id]++;
				return false;
			}
			</script>
			<?php
			$i = 0;
			foreach($wp_spam_question_filter_questions as $question) {
				wp_spam_question_filter_template($i, $question, $wp_spam_question_filter_answers[$i]);
				$i++;
			}
			wp_spam_question_filter_template($i, '', array());
		?>
		</table>

	<?php submit_button(); ?>
	</form>

	<p>WP Spam Question Filter version <?php echo wp_spam_question_filter_VERSION; ?> by <a href="http://seravo.fi/">Seravo Oy</a>.</p>

</div>
<?php
}

?>
