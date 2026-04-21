<?php 
// Define the plugin class
class Stairbuilder_Plugin {
  
    // Define the constructor method
    public function __construct() {
      // Add the shortcode
      add_shortcode( 'stairbuilder_form', array( $this, 'generate_shortcode' ) );
    }
    
    // Define a method to generate the shortcode
    public function generate_shortcode() {
     
      // Get the form data
      $form_data = $this->get_form_data();
      
      // Generate the shortcode
      ob_start();
      include( plugin_dir_path( __FILE__ ) . '../front/form-template.php' );
      $output = ob_get_clean();
      return $output;
    }
    
    // Define a method to get the form data
    private function get_form_data() {
      // Define the form data
      $form_data = array(
        'step_width' => '',
        'step_depth' => '',
        'stair_type' => '',
      );
      
      // Set the form data if it's been submitted
      if ( isset( $_POST['step_width'] ) ) {
        $form_data['step_width'] = sanitize_text_field( $_POST['step_width'] );
      }
      if ( isset( $_POST['step_depth'] ) ) {
        $form_data['step_depth'] = sanitize_text_field( $_POST['step_depth'] );
      }
      if ( isset( $_POST['stair_type'] ) ) {
        $form_data['stair_type'] = sanitize_text_field( $_POST['stair_type'] );
      }
      
      return $form_data;
    }
    
    // Define a method to process the form data
    public function process_form_data() {
      // Process the form data
      $form_data = $this->get_form_data();
      
      $step_width = $form_data['step_width'];
      $step_depth = $form_data['step_depth'];
      $stair_type = $form_data['stair_type'];
      
      // Do something with the form data
    }
  }
  
  // Instantiate the plugin class
  $stairbuilder_plugin = new Stairbuilder_Plugin();