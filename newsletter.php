<?php

include_once (dirname(__FILE__) . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR. 'util.php');
include_once (dirname(__FILE__) . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR. 'db.php');
include_once (dirname(__FILE__) . DIRECTORY_SEPARATOR . 'settings.php');

$newsletter_email_subject = 'Newsletter from ' . $_SERVER["HTTP_HOST"];

global $private_key;
$subscribed_emails = get_all_emails();

$status = 'forbidden';
$display_content = true;
$http_method = strtolower($_SERVER['REQUEST_METHOD']);
$action = strtolower($_REQUEST['action']);

if ($action == 'subscribe') {
    $ok = add_email_address($_REQUEST['email']);
    send_response($ok ? 200 : 500);
} elseif ($action == 'unsubscribe') {
    if(remove_email_address($_REQUEST['user'])){
        $status = 'unsubscribe_successful';
    }else{
        send_response(-1);
    }
}elseif ($_REQUEST['pk'] == $private_key){
    $status = 'display_form';
    if ($http_method == 'post') {
        send_newsletter();
    }
}

function send_response($code)
{
    global $display_content;
    $display_content = false;
    if ($code == 200) {
        header('HTTP/1.1 200 OK');
    } elseif ($code == 500) {
        header('HTTP/1.1 500 Internal Server Error');
    } else {
        $home_url = 'http://' . $_SERVER["HTTP_HOST"];
        header("Location: $home_url");
    }
}

function send_newsletter()
{
    global $my_email;
    global $subscribed_emails;
    global $newsletter_email_subject;
    $emails_to_send = $_POST['emails'];
    $orig_tmp_file_name = $_FILES['newsletter-template']['tmp_name'];
    $content = '';
    $file = fopen($orig_tmp_file_name, 'r');
    if ($file) {
        while (!feof($file)) {
            $content .= fgets($file);
        }
        fclose($file);
    }
    $content = str_replace('</body>', '', $content);
    $content = str_replace('</BODY>', '', $content);
    $content = str_replace('</html>', '', $content);
    $content = str_replace('</HTML>', '', $content);

    $headers = "Content-type: text/html; charset=iso-8859-1\r\n";
    $headers .= "From: $my_email\r\n";

    $base_unsubscribe_url = str_replace('send-newsletter.php', 'newsletter.php', 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    foreach ($emails_to_send as $email_key) {
        $email = $subscribed_emails[$email_key];
        $unsubscribe_url = $base_unsubscribe_url . '?action=unsubscribe&amp;user=' . $email_key;
        $message = $content . '<p>To stop receiving emails from ' . $_SERVER["HTTP_HOST"] . ', unsubscribe <a href="' . $unsubscribe_url . '">here</a>.</p>';
        $message .= '</body></html>';
        mail($email, $newsletter_email_subject, $message, $headers);
    }
}
?>

<?php if ($display_content) { ?>
<!DOCTYPE HTML>
<!--[if IE 8]> <html class="ie8 no-js"> <![endif]-->
<!--[if (gte IE 9)|!(IE)]><!--> <html class="no-js"> <!--<![endif]-->
<head>
	<!-- begin meta -->
	<meta charset="utf-8">
	<meta name="description" content="Finesse is a responsive business and portfolio theme carefully handcrafted using the latest technologies. It features a clean design, as well as extended functionality that will come in very handy.">
	<meta name="keywords" content="Finesse, responsive business portfolio theme, HTML5, CSS3, clean design">
	<meta name="author" content="Ixtendo">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
	<!-- end meta -->
	
	<!-- begin CSS -->
	<link href="style.css" type="text/css" rel="stylesheet" id="main-style">
	<!--[if IE]> <link href="css/ie.css" type="text/css" rel="stylesheet"> <![endif]-->
	<link href="css/colors/orange.css" type="text/css" rel="stylesheet" id="color-style">
    <!-- end CSS -->
	
	<link href="images/favicon.ico" type="image/x-icon" rel="shortcut icon">
	
	<!-- begin JS -->
    <script src="js/jquery-1.7.2.min.js" type="text/javascript"></script> <!-- jQuery -->
    <script src="js/ie.js" type="text/javascript"></script> <!-- IE detection -->
    <script src="js/jquery.easing.1.3.js" type="text/javascript"></script> <!-- jQuery easing -->
	<script src="js/modernizr.custom.js" type="text/javascript"></script> <!-- Modernizr -->
    <!--[if IE 8]><script src="js/respond.min.js" type="text/javascript"></script><![endif]--> <!-- Respond -->
	<script src="js/jquery.polyglot.language.switcher.js" type="text/javascript"></script> <!-- language switcher -->
    <script src="js/ddlevelsmenu.js" type="text/javascript"></script> <!-- drop-down menu -->
    <script type="text/javascript"> <!-- drop-down menu -->
        ddlevelsmenu.setup("nav", "topbar");
    </script>
    <script src="js/tinynav.min.js" type="text/javascript"></script> <!-- tiny nav -->
    <script src="js/jquery.ui.totop.min.js" type="text/javascript"></script> <!-- scroll to top -->
    <script src="js/jquery.validate.min.js" type="text/javascript"></script> <!-- form validation -->
	<script src="js/jquery.tweet.js" type="text/javascript"></script> <!-- Twitter widget -->
	<script src="js/jquery.touchSwipe.min.js" type="text/javascript"></script> <!-- touchSwipe -->
    <script src="js/custom.js" type="text/javascript"></script> <!-- jQuery initialization -->
    <!-- end JS -->
	
	<title>Finesse - Newsletter</title>
</head>

<body>
<!-- begin container -->
<div id="wrap">
	<!-- begin header -->
        <header id="header">
            <!-- begin header top -->
            <section id="header-top" class="clearfix">
                <!-- begin header left -->
                <div class="one-half">
                    <h1 id="logo"><a href="index.html"><img src="images/logo.png" alt="Finesse"></a></h1>
                    <p id="tagline">Responsive Business Theme</p>
                </div>
                <!-- end header left -->
                
                <!-- begin header right -->
                <div class="one-half column-last">
                    <!-- begin language switcher -->
                    <div id="polyglotLanguageSwitcher">
                        <form action="#">
                            <select id="polyglot-language-options">
                                <option id="en" value="en" selected>English</option>
                                <option id="fr" value="fr">Fran&ccedil;ais</option>
                                <option id="de" value="de">Deutsch</option>
                                <option id="it" value="it">Italiano</option>
                                <option id="es" value="es">Espa&ntilde;ol</option>
                            </select>
                        </form>
                    </div>
                    <!-- end language switcher -->
                    
                    <!-- begin contact info -->
                    <div class="contact-info">
                        <p class="phone">(123) 456-7890</p>
                        <p class="email"><a href="mailto:info@finesse.com">info@finesse.com</a></p>
                    </div>
                    <!-- end contact info -->
                </div>
                <!-- end header right -->
            </section>
            <!-- end header top -->
            
            <!-- begin navigation bar -->
            <section id="navbar" class="clearfix">
                <!-- begin navigation -->
                <nav id="nav">
                    <ul id="navlist" class="clearfix">
                        <li><a href="index.html" rel="submenu1">Home</a>
                        	<ul id="submenu1" class="ddsubmenustyle">
                                <li><a href="index.html">Home Version 1</a></li>
                                <li><a href="index-2.html">Home Version 2</a></li>
                            </ul>
                        </li>
                        <li><a href="about-us.html" rel="submenu2">Templates</a>
                            <ul id="submenu2" class="ddsubmenustyle">
                            	<li><a href="about-us.html">About Us</a></li>
                            	<li><a href="services.html">Services</a></li>
                                <li><a href="testimonials.html">Testimonials</a></li>
                                <li><a href="sitemap.html">Sitemap</a></li>
                                <li><a href="404-error-page.html">404 Error Page</a></li>
                                <li><a href="search-results.html">Search Results</a></li>
                                <li><a href="full-width-page.html">Full Width Page</a></li>
                                <li><a href="page-right-sidebar.html">Page with Right Sidebar</a></li>
                                <li><a href="page-left-sidebar.html">Page with Left Sidebar</a></li>
                                <li><a href="#">Multi-Level Drop-Down</a>
                                    <ul>
                                        <li><a href="#">Drop-Down Example</a></li>
                                        <li><a href="#">Multi-Level Drop-Down</a>
                                            <ul>
                                                <li><a href="#">Drop-Down Example</a></li>
                                                <li><a href="#">Drop-Down Example</a></li>
                                                <li><a href="#">Drop-Down Example</a></li>
                                            </ul>
                                        </li>
                                        <li><a href="#">Drop-Down Example</a></li>
                                    </ul>
                                </li>
                            </ul>
                        </li>
                        <li><a href="elements.html" rel="submenu3">Features</a>
                            <ul id="submenu3" class="ddsubmenustyle">
                                <li><a href="elements.html">Elements</a></li>
                                <li><a href="grid-columns.html">Grid Columns</a></li>
                                <li><a href="pricing-tables.html">Pricing Tables</a></li>
                                <li><a href="images.html">Images</a></li>
                                <li><a href="video.html">Video</a></li>
                            </ul>
                        </li>
                        <li><a href="portfolio.html" rel="submenu4">Portfolio</a>
                            <ul id="submenu4" class="ddsubmenustyle">
                                <li><a href="portfolio.html">Portfolio Overview</a></li>
                                <li><a href="portfolio-item-slider.html">Portfolio Item &ndash; Slider</a></li>
                                <li><a href="portfolio-item-image.html">Portfolio Item &ndash; Image</a></li>
                                <li><a href="portfolio-item-embedded-video.html">Portfolio Item &ndash; Embedded Video</a></li>
                                <li><a href="portfolio-item-self-hosted-video.html">Portfolio Item &ndash; Self-Hosted Video</a></li>
                            </ul>
                        </li>
                        <li><a href="blog.html" rel="submenu5">Blog</a>
                            <ul id="submenu5" class="ddsubmenustyle">
                                <li><a href="blog.html">Blog Overview</a></li>
                                <li><a href="blog-post.html">Blog Post</a></li>
                            </ul>
                        </li>
                        <li><a href="contact.html">Contact</a></li>
                    </ul>
                </nav>
                <!-- end navigation -->
                
                <!-- begin search form -->
                <form id="search-form" action="search.php" method="get">
                    <input id="s" type="text" name="s" placeholder="Search &hellip;" style="display: none;">
                    <input id="search-submit" type="submit" name="search-submit" value="Search">
                </form>
                <!-- end search form -->
            </section>
            <!-- end navigation bar -->
            
        </header>
        <!-- end header -->
        
	<!-- begin content -->
        <section id="content" class="container clearfix">
        	<!-- begin page header -->
            <header id="page-header">
            	<h1 id="page-title">Newsletter</h1>
                <?php if ($status == 'display_form') { ?>
                <div>
                    <a href="admin.php?pk=<?php echo $private_key;?>">Administration Dashboard</a>
                </div>
                <?php } ?>
            </header>
            <!-- end page header -->
            
            <!-- begin main content -->
            <section id="main" class="three-fourths">
                <?php if ($status == 'display_form') { ?>
                <?php if ($http_method == 'post') { ?>
                    <div class="notification-box notification-box-success">
                        <p>The newsletter has been sent.</p>
                        <a href="#" class="notification-close notification-close-success">x</a>
                    </div>
                    <?php } ?>
                <form id="send-newsletter-form" class="content-form" method="post" enctype="multipart/form-data" action="<?=$_SERVER['PHP_SELF']?>">
                    <p>
                        <label for="emails">Select email addresses:<span class="note">*</span></label>
                        <select id="emails" multiple size="10" name="emails[]" class="required">
                            <?php
                            foreach ($subscribed_emails as $id => $value) {
                                echo '<option selected value="' . $id . '"> ' . $value . '  </option>';
                            }
                            ?>
                        </select>
                    </p>
                    <p>
                        <label for="newsletter-template">Upload newsletter template:<span class="note">*</span></label>
                        <input id="newsletter-template" type="file" name="newsletter-template" class="required">
                    </p>
                    <p>
                        <input type="hidden" name="pk" value="<?php echo $private_key;?>">
                        <input id="submit" class="button" type="submit" name="submit" value="Send Newsletter">
                    </p>
                </form>
                <?php } elseif($status == 'unsubscribe_successful') { ?>
                <div class="notification-box notification-box-success">
                    <p>You have been successfully unsubscribed from <a href="<?php echo $_SERVER["HTTP_HOST"]?>"><?php echo $_SERVER["HTTP_HOST"]?></a>.</p>
                    <a href="#" class="notification-close notification-close-success">x</a>
                </div>
                <?php } else { ?>
                <div class="notification-box notification-box-error">
                    <p>You have no rights to access this page.</p>
                    <a href="#" class="notification-close notification-close-error">x</a>
                </div>
                <?php } ?>
            </section>
            <!-- end main content -->
            
            <!-- begin sidebar -->
            <aside id="sidebar" class="one-fourth column-last">
            	<div class="widget">
					<h3>Secondary Navigation</h3>
					<nav>
						<ul class="menu">
							<li><a href="#">Example of a Link</a></li>
							<li class="current-menu-item"><a href="#">The Active Link</a></li>
							<li><a href="#">Example of a Link</a></li>
							<li><a href="#">Example of a Link</a></li>
						</ul>
					</nav>
				</div>
            </aside>
            <!-- end sidebar -->
        </section>
        <!-- end content -->             
    
	<!-- begin footer -->
	<footer id="footer">
    	<div class="container">
            <!-- begin footer top -->
            <div id="footer-top">
                <div class="one-fourth">
                	<div class="widget">
                        <h3>About Us</h3>
                        <p>Finesse is a responsive business and portfolio theme carefully handcrafted using the latest technologies.</p>
                        <p>It features a clean design, as well as extended functionality that will come in very handy.</p>
                    </div>
                </div>
                <div class="one-fourth">
                	<div class="widget latest-posts">
                        <h3>Latest Posts</h3>
                        <ul>
                            <li>
                                <a href="blog-post.html">How to Make Innovative Ideas Happen</a>
                                <span>March 10, 2012</span>
                            </li>
                            <li> <a href="blog-post.html">Web Development for the iPhone and iPad</a>
                                <span>March 10, 2012</span>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="one-fourth">
                	<div class="widget twitter-widget">
                    	<h3>Latest Tweets</h3>
                        <div class="tweet"></div>
                    </div>
                </div>
                <div class="one-fourth column-last">
                	<div class="widget contact-info">
                    	<h3>Contact Info</h3>
                        <p class="address"><strong>Address:</strong> 123 Street, City, Country</p>
                        <p class="phone"><strong>Phone:</strong> (123) 456-7890</p>
                        <p class="email"><strong>Email:</strong> <a href="mailto:info@finesse.com">info@finesse.com</a></p>
                        <div class="social-links">
                        	<h4>Follow Us</h4>
                            <ul>
                            	<li class="twitter"><a href="#" title="Twitter" target="_blank">Twitter</a></li>
                                <li class="facebook"><a href="#" title="Facebook" target="_blank">Facebook</a></li>
                                <li class="google"><a href="#" title="Google+" target="_blank">Google+</a></li>
                                <li class="youtube"><a href="#" title="YouTube" target="_blank">YouTube</a></li>
                                <li class="skype"><a href="#" title="Skype" target="_blank">Skype</a></li>
                                <li class="rss"><a href="#" title="RSS" target="_blank">RSS</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <!-- end footer top -->	
            
            <!-- begin footer bottom -->
            <div id="footer-bottom">
            	<div class="one-half">
                	<p>Copyright &copy; 2012 Finesse. Created by <a href="http://themeforest.net/user/ixtendo">Ixtendo</a>.</p>
                </div>
                
                <div class="one-half column-last">
                	<nav id="footer-nav">
                        <ul>
                            <li><a href="index.html">Home</a> &middot;</li>
                            <li><a href="about-us.html">Templates</a> &middot;</li>
                            <li><a href="elements.html">Features</a> &middot;</li>
                            <li><a href="portfolio.html">Portfolio</a> &middot;</li>
                            <li><a href="blog.html">Blog</a> &middot;</li>
                            <li><a href="contact.html">Contact</a></li>
                        </ul>
                    </nav>
                </div>
            </div>
            <!-- end footer bottom -->	
        </div>
	</footer>
	<!-- end footer -->
</div>
<!-- end container -->

</body>
</html>
<?php } ?>