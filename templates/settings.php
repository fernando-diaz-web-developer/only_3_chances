<div>
  <?php screen_icon(); ?>
  <h2>Only 3 Chances</h2>
  <form method="post" action="options.php">
  <?php settings_fields( 'o3c_settings' );
  do_settings_sections( 'o3c_settings' );
 submit_button(); ?>
  </form>
  </div>