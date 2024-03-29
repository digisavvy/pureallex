<?php
/**
 * Customize Panel Class.
 *
 * A UI container for sections, managed by the WP_Customize_Manager.
 *
 * @package WordPress
 * @subpackage Customize
 * @since 4.0.0
 */
class WP_Customize_Panel {

	/**
	 * WP_Customize_Manager instance.
	 *
	 * @since 4.0.0
	 * @access public
	 * @var WP_Customize_Manager
	 */
	public $manager;

	/**
	 * Unique identifier.
	 *
	 * @since 4.0.0
	 * @access public
	 * @var string
	 */
	public $id;

	/**
	 * Priority of the panel, defining the display order of panels and sections.
	 *
	 * @since 4.0.0
	 * @access public
	 * @var integer
	 */
	public $priority = 160;

	/**
	 * Capability required for the panel.
	 *
	 * @since 4.0.0
	 * @access public
	 * @var string
	 */
	public $capability = 'edit_theme_options';

	/**
	 * Theme feature support for the panel.
	 *
	 * @since 4.0.0
	 * @access public
	 * @var string|array
	 */
	public $theme_supports = '';

	/**
	 * Title of the panel to show in UI.
	 *
	 * @since 4.0.0
	 * @access public
	 * @var string
	 */
	public $title = '';

	/**
	 * Description to show in the UI.
	 *
	 * @since 4.0.0
	 * @access public
	 * @var string
	 */
	public $description = '';

	/**
	 * Customizer sections for this panel.
	 *
	 * @since 4.0.0
	 * @access public
	 * @var array
	 */
	public $sections;

	/**
	 * @since 4.1.0
	 * @access public
	 * @var string
	 */
	public $type;

	/**
	 * Callback.
	 *
	 * @since 4.1.0
	 * @access public
	 *
	 * @see WP_Customize_Section::active()
	 *
	 * @var callable Callback is called with one argument, the instance of
	 *               WP_Customize_Section, and returns bool to indicate whether
	 *               the section is active (such as it relates to the URL
	 *               currently being previewed).
	 */
	public $active_callback = '';

	/**
	 * Constructor.
	 *
	 * Any supplied $args override class property defaults.
	 *
	 * @since 4.0.0
	 *
	 * @param WP_Customize_Manager $manager Customizer bootstrap instance.
	 * @param string               $id      An specific ID for the panel.
	 * @param array                $args    Panel arguments.
	 */
	public function __construct( $manager, $id, $args = array() ) {
		$keys = array_keys( get_object_vars( $this ) );
		foreach ( $keys as $key ) {
			if ( isset( $args[ $key ] ) ) {
				$this->$key = $args[ $key ];
			}
		}

		$this->manager = $manager;
		$this->id = $id;
		if ( empty( $this->active_callback ) ) {
			$this->active_callback = array( $this, 'active_callback' );
		}

		$this->sections = array(); // Users cannot customize the $sections array.

		return $this;
	}

	/**
	 * Check whether panel is active to current Customizer preview.
	 *
	 * @since 4.1.0
	 * @access public
	 *
	 * @return bool Whether the panel is active to the current preview.
	 */
	public final function active() {
		$panel = $this;
		$active = call_user_func( $this->active_callback, $this );

		/**
		 * Filter response of WP_Customize_Panel::active().
		 *
		 * @since 4.1.0
		 *
		 * @param bool                 $active  Whether the Customizer panel is active.
		 * @param WP_Customize_Panel $panel WP_Customize_Panel instance.
		 */
		$active = apply_filters( 'customize_panel_active', $active, $panel );

		return $active;
	}

	/**
	 * Default callback used when invoking WP_Customize_Panel::active().
	 *
	 * Subclasses can override this with their specific logic, or they may
	 * provide an 'active_callback' argument to the constructor.
	 *
	 * @since 4.1.0
	 * @access public
	 *
	 * @return bool Always true.
	 */
	public function active_callback() {
		return true;
	}

	/**
	 * Gather the parameters passed to client JavaScript via JSON.
	 *
	 * @since 4.1.0
	 *
	 * @return array The array to be exported to the client as JSON
	 */
	public function json() {
		$array = wp_array_slice_assoc( (array) $this, array( 'title', 'description', 'priority', 'type' ) );
		$array['content'] = $this->get_content();
		$array['active'] = $this->active();
		return $array;
	}

	/**
	 * Checks required user capabilities and whether the theme has the
	 * feature support required by the panel.
	 *
	 * @since 4.0.0
	 *
	 * @return bool False if theme doesn't support the panel or the user doesn't have the capability.
	 */
	public final function check_capabilities() {
		if ( $this->capability && ! call_user_func_array( 'current_user_can', (array) $this->capability ) ) {
			return false;
		}

		if ( $this->theme_supports && ! call_user_func_array( 'current_theme_supports', (array) $this->theme_supports ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get the panel's content template for insertion into the Customizer pane.
	 *
	 * @since 4.1.0
	 *
	 * @return string
	 */
	public final function get_content() {
		ob_start();
		$this->maybe_render();
		$template = trim( ob_get_contents() );
		ob_end_clean();
		return $template;
	}

	/**
	 * Check capabilities and render the panel.
	 *
	 * @since 4.0.0
	 */
	public final function maybe_render() {
		if ( ! $this->check_capabilities() ) {
			return;
		}

		/**
		 * Fires before rendering a Customizer panel.
		 *
		 * @since 4.0.0
		 *
		 * @param WP_Customize_Panel $this WP_Customize_Panel instance.
		 */
		do_action( 'customize_render_panel', $this );

		/**
		 * Fires before rendering a specific Customizer panel.
		 *
		 * The dynamic portion of the hook name, $this->id, refers to the ID
		 * of the specific Customizer panel to be rendered.
		 *
		 * @since 4.0.0
		 */
		do_action( "customize_render_panel_{$this->id}" );

		$this->render();
	}

	/**
	 * Render the panel container, and then its contents.
	 *
	 * @since 4.0.0
	 * @access protected
	 */
	protected function render() {
		?>
		<li id="accordion-panel-<?php echo esc_attr( $this->id ); ?>" class="control-section control-panel accordion-section">
			<h3 class="accordion-section-title" tabindex="0">
				<?php echo esc_html( $this->title ); ?>
				<span class="screen-reader-text"><?php _e( 'Press return or enter to open this panel' ); ?></span>
			</h3>
			<ul class="accordion-sub-container control-panel-content">
				<?php $this->render_content(); ?>
			</ul>
		</li>
		<?php
	}

	/**
	 * Render the sections that have been added to the panel.
	 *
	 * @since 4.1.0
	 * @access protected
	 */
	protected function render_content() {
		?>
		<li class="panel-meta accordion-section control-section<?php if ( empty( $this->description ) ) { echo ' cannot-expand'; } ?>">
			<div class="accordion-section-title" tabindex="0">
				<span class="preview-notice"><?php
					/* translators: %s is the site/panel title in the Customizer */
					echo sprintf( __( 'You are customizing %s' ), '<strong class="panel-title">' . esc_html( $this->title ) . '</strong>' );
				?></span>
			</div>
			<?php if ( ! empty( $this->description ) ) : ?>
				<div class="accordion-section-content description">
					<?php echo $this->description; ?>
				</div>
			<?php endif; ?>
		</li>
		<?php
	}
}
