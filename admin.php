<?php
$paths = array(realpath(dirname(__FILE__) . DIRECTORY_SEPARATOR));
set_include_path(implode(PATH_SEPARATOR, $paths));

require_once 'Zend/Search/Lucene.php';
include_once (dirname(__FILE__) . DIRECTORY_SEPARATOR . 'settings.php');
include_once (dirname(__FILE__) . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'util.php');
include_once (dirname(__FILE__) . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'db.php');

global $my_email;
global $private_key;
global $server_path;
$all_settings = get_all_settings();

$status = 'forbidden';
$success_msg = '';
$error_msg = '';
$warning_msg = '';
$http_method = strtolower($_SERVER['REQUEST_METHOD']);
$action = $_REQUEST['action'];

if ((strlen(trim($server_path)) == 0)) {
    $settings_file_name = dirname(__FILE__) . DIRECTORY_SEPARATOR . "settings.php";
    $file = fopen($settings_file_name, 'a');
    if ($file) {
        fwrite($file, "\n\$server_path='" . $settings_file_name . "';");
        fclose($file);
    }
}

if ((strlen(trim($private_key)) == 0)) {
    if ($http_method == 'get') {
        $status = 'private_key_must_be_generated';
    } elseif ($http_method == 'post') {
        generate_private_key();
        $status = 'private_key_generated';
    }
} elseif ($_REQUEST['pk'] == $private_key) {
    $status = 'display_settings';
    if ($http_method == 'get') {
        if ($action == 'rsi') {
            rebuild_search_indexes();
        }
    } elseif ($http_method == 'post') {
        if ($_POST['submit'] == 'restore') {
            $all_settings = restore_settings();
            $success_msg = 'The settings were successfully restored.';
        } else {
            try{
                $all_settings = save_custom_settings();
                $success_msg = 'The settings were successfully saved.';
            }catch (Exception $e) {
                $error_msg = $e->getMessage();
            }
        }
    }
}

function generate_private_key()
{
    global $my_email;
    $private_key = uniqid();
    $subject = 'Your Admin Page Private Link';
    $headers = "Content-type: text/html; charset=iso-8859-1\r\n";
    $headers .= "From: $my_email\r\n";
    $admin_page_url = 'http://' . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
    if (strpos($admin_page_url, '?')) {
        $admin_page_url = substr($admin_page_url, 0, strpos($admin_page_url, '?'));
    }
    $admin_page_url .= '?pk=' . $private_key;
    $message = '<html><body>Your admin page private link is <a href="' . $admin_page_url . '">' . $admin_page_url . '</a>.</body></html>';

    $file_name = dirname(__FILE__) . DIRECTORY_SEPARATOR . "settings.php";
    $file = fopen($file_name, 'a');
    if ($file) {
        fwrite($file, "\n\$private_key='" . $private_key . "';");
        fclose($file);
    }
    mail($my_email, $subject, $message, $headers);
}

function save_custom_settings()
{
    global $all_settings;
    $new_settings = array();
    $search_result_items = max(1, intval($_POST['search-result-items']));
    $public_html_dir = $_POST['public-html-dir'];
    $search_indexes_folder = $_POST['search-indexes-folder'];
    $search_display_meta_tag = $_POST['search-display-meta-tag'];
    $search_dynamic_pages = encode_dynamic_urls($_POST['search-dynamic-pages']);
    $search_exclude_from_indexing = encode_dynamic_urls($_POST['search-exclude-from-indexing']);

    check_public_html_existence($public_html_dir);

    if (get_setting('public_html_dir', $all_settings) != $public_html_dir) {
        $new_settings['public_html_dir'] = $public_html_dir;
    }
    if (get_setting('search_result_items', $all_settings) != $search_result_items) {
        $new_settings['search_result_items'] = $search_result_items;
    }
    if (get_setting('search_indexes_folder', $all_settings) != $search_indexes_folder) {
        $new_settings['search_indexes_folder'] = $search_indexes_folder;
    }
    if (get_setting('search_display_meta_tag', $all_settings) != $search_display_meta_tag) {
        $new_settings['search_display_meta_tag'] = $search_display_meta_tag;
    }
    if (get_setting('search_dynamic_pages', $all_settings) != $search_dynamic_pages) {
        $new_settings['search_dynamic_pages'] = $search_dynamic_pages;
    }
    if (get_setting('search_exclude_from_indexing', $all_settings) != $search_exclude_from_indexing) {
        $new_settings['search_exclude_from_indexing'] = $search_exclude_from_indexing;
    }
    save_settings($new_settings);

    $return_array = array();
    foreach ($all_settings as $key => $value) {
        $return_array[$key] = $value;
    }
    foreach ($new_settings as $key => $value) {
        $return_array[$key]['custom_value'] = $value;
    }
    return $return_array;
}

function can_index_html_file($html_file)
{
    global $all_settings;
    foreach (get_dynamic_urls(get_setting('search_exclude_from_indexing', $all_settings)) as $excluded_file) {
        $excluded_file = rtrim(str_replace("\\", "/", $excluded_file), '/');
        $pos = strpos($html_file, $excluded_file);
        if($pos && $pos >= 0){
            return false;
        }
    }
    return true;
}

function rebuild_search_indexes()
{
    global $success_msg;
    global $error_msg;
    global $warning_msg;
    global $all_settings;
    $index_folder = get_setting('search_indexes_folder', $all_settings);
    try {
        $index = new Zend_Search_Lucene($index_folder, true);
        setlocale(LC_CTYPE, 'en_US');
        foreach (get_all_html_files(dirname(__FILE__)) as $html_file => $html_url) {
            if(can_index_html_file($html_file)){
                $file_content = file_get_contents($html_file);
                $file_content = '<html>'.strstr($file_content, '<head');
                $doc = Zend_Search_Lucene_Document_Html::loadHTML($file_content);
                $doc->addField(Zend_Search_Lucene_Field::Text('url', $html_url, 'UTF-8'));
                $index->addDocument($doc);
                flush();
            }
        }

        $broken_urls = array();
        foreach (get_dynamic_urls(get_setting('search_dynamic_pages', $all_settings)) as $url) {
            $headers = get_headers($url);
            if(strrpos($headers[0], '200')){
                $content = file_get_contents($url);
                $content = '<html>'.strstr($content, '<head');
                $doc = Zend_Search_Lucene_Document_Html::loadHTML($content);
                $doc->addField(Zend_Search_Lucene_Field::Text('url', $url, 'UTF-8'));
                $index->addDocument($doc);
                flush();
            }else{
                array_push($broken_urls, $url);
            }
        }

        if (file_exists($index_folder)) {
            if(count($broken_urls)>0){
                $warning_msg = '<p>The website was successfully indexed, but the following URL\'s were skipped because they are broken:</p>';
                $warning_msg .= '<ul class="disc">';
                foreach($broken_urls as $broken_url){
                    $warning_msg .= '<li><a href="'.$broken_url.'">'.$broken_url.'</a></li>';
                }
                $warning_msg .= '</ul>';
                $warning_msg .= '<p>Please remove them from the "List of dynamic pages" field.</p>';
            }else{
                $success_msg = 'The website was successfully indexed.';
            }
        } else {
            $error_msg = 'An error occurred during the website indexing. The error message is: the folder that stores the website indexes couldn\'t be created';
        }
    } catch (Exception $e) {
        $error_msg = 'An error occurred during the website indexing. The error message is: ' . $e->getMessage();
    }
}

function get_all_html_files($path)
{
    $html_files = array();
    $path = rtrim(str_replace("\\", "/", $path), '/') . '/';
    $entries = array();
    $dir = dir($path);
    while (false !== ($entry = $dir->read())) {
        $entries[] = $entry;
    }
    $dir->close();
    foreach ($entries as $entry) {
        $fullname = $path . $entry;
        if ($entry != '.' && $entry != '..' && is_dir($fullname)) {
            $html_files = array_merge($html_files, get_all_html_files($fullname));
        } else if (is_file($fullname) && (strcasecmp(substr($fullname, strlen($fullname) - 5), '.html') == 0)) {
            $html_files[$fullname] = get_url_for_html_file($path, $entry);
        }
    }
    return $html_files;
}

function get_url_for_html_file($path, $entry)
{
    global $all_settings;
    $html_file = str_replace("\\", "/", $path . $entry);
    $public_html_dir = '/'.get_setting('public_html_dir', $all_settings).'/';
    $site_url = 'http://' . $_SERVER["HTTP_HOST"];
    $i = strrpos($html_file, $public_html_dir);
    if ($i && $i >= 0) {
        return $site_url . '/' . substr($html_file, $i + strlen($public_html_dir));
    } else {
        return $site_url . '/' . $entry;
    }
}

?>
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
	
	<title>Finesse - Administration</title>
</head>

<body>
<!-- begin container -->
<div id="wrap">
	<!-- begin header -->
        <header id="header" class="container">
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
            	<h1 id="page-title">Administration Dashboard</h1>	
            </header>
            <!-- end page header -->

            <!-- begin main content -->
            <section id="main" class="three-fourths">
                <?php if ($status == 'private_key_must_be_generated') { ?>
                <form id="admin-settings-form" class="content-form" method="post" action="<?=$_SERVER['PHP_SELF']?>">
                    <p>This page is locked. To unlock it, click the button bellow.</p>
                    <input class="button" type="submit" name="submit" value="Unlock Page">
                </form>
                <?php } elseif ($status == 'private_key_generated') { ?>
                <div class="notification-box notification-box-success">
                    <p>The link you must use to access the administration page has been successfully sent to your email.</p>
                    <a href="#" class="notification-close notification-close-success">x</a>
                </div>
                <?php } elseif ($status == 'display_settings') { ?>
                <?php if ($success_msg != '') { ?>
                <div class="notification-box notification-box-success">
                    <p><?php echo $success_msg; ?></p>
                    <a href="#" class="notification-close notification-close-success">x</a>
                </div>
                <?php } elseif ($error_msg != '') { ?>
                <div class="notification-box notification-box-error">
                    <p><?php echo $error_msg; ?></p>
                    <a href="#" class="notification-close notification-close-error">x</a>
                </div>
                <?php } elseif ($warning_msg != '') { ?>
                <div class="notification-box notification-box-warning">
                    <?php echo $warning_msg; ?>
                    <a class="notification-close notification-close-warning" href="#">x</a>
                </div>
                <?php } ?>
                <form id="admin-settings-form" class="content-form" method="post" enctype="multipart/form-data" action="<?=$_SERVER['PHP_SELF']?>">
                    <p>
                        <label for="public-html-dir">The name of the folder where the website is located:<span class="note">*</span></label>
                        <input id="public-html-dir" type="text" name="public-html-dir" value="<?php echo get_setting('public_html_dir', $all_settings); ?>" class="required">
                    </p>
                    <p>
                        <label for="search-indexes-folder">Path to the folder that contains the website indexes:<span class="note">*</span></label>
                        <input id="search-indexes-folder" type="text" name="search-indexes-folder" value="<?php echo get_setting('search_indexes_folder', $all_settings); ?>" class="required">
                    </p>
                    <p>
                        <label for="search-display-meta-tag">Meta tag name used for the short description:<span class="note">*</span></label>
                        <input id="search-display-meta-tag" type="text" name="search-display-meta-tag" value="<?php echo get_setting('search_display_meta_tag', $all_settings); ?>" class="required">
                    </p>
                    <p>
                        <label for="search-result-items">Search results items per page:<span class="note">*</span></label>
                        <input id="search-result-items" size="3" type="text" name="search-result-items" value="<?php echo get_setting('search_result_items', $all_settings); ?>" class="required">
                    </p>
                    <p>
                        <label for="search-dynamic-pages">List of dynamic pages (Enter one URL per line.):</label>
                        <textarea id="search-dynamic-pages" rows="3" cols="7" name="search-dynamic-pages"><?php echo base64_decode(get_setting('search_dynamic_pages', $all_settings)); ?></textarea>
                    </p>
                    <p>
                        <label for="search-exclude-from-indexing">List of HTML files or folders which must be excluded from indexing (Enter one per line.):</label>
                        <textarea id="search-exclude-from-indexing" rows="3" cols="7" name="search-exclude-from-indexing"><?php echo base64_decode(get_setting('search_exclude_from_indexing', $all_settings)); ?></textarea>
                    </p>
                    <p>
                        <input type="hidden" name="pk" value="<?php echo $private_key;?>">
                        <input id="restore" class="button" type="submit" name="submit" value="Restore Defaults">
                        <input id="submit" class="button" type="submit" name="submit" value="Save Settings">
                    </p>
                </form>
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
					<h3>Other Settings</h3>
					<nav>
						<ul class="menu">
                            <li><a href="newsletter.php?pk=<?php echo $private_key;?>">Send newsletter</a></li>
                            <li><a href="admin.php?action=rsi&amp;pk=<?php echo $private_key;?>">Rebuild search indexes</a></li>
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