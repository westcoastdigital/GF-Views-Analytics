<?php
/**
 * GitHub Plugin Updater
 * Handles automatic updates from GitHub repositories
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('SimpliWeb_GitHub_Updater')) {
    class SimpliWeb_GitHub_Updater {
        private $file;
        private $plugin;
        private $basename;
        private $active;
        private $username;
        private $repository;
        private $authorize_token;
        private $github_response;

        /**
         * Constructor
         * 
         * @param string $file Path to the main plugin file
         */
        public function __construct($file) {
            $this->file = $file;

            add_action('admin_init', array($this, 'set_plugin_properties'));

            return $this;
        }

        /**
         * Set plugin properties from WordPress
         */
        public function set_plugin_properties() {
            $this->plugin = get_plugin_data($this->file);
            $this->basename = plugin_basename($this->file);
            $this->active = is_plugin_active($this->basename);
        }

        /**
         * Set GitHub username
         * 
         * @param string $username GitHub username or organization
         */
        public function set_username($username) {
            $this->username = $username;
        }

        /**
         * Set GitHub repository name
         * 
         * @param string $repository Repository name
         */
        public function set_repository($repository) {
            $this->repository = $repository;
        }

        /**
         * Set authorization token for private repositories
         * 
         * @param string $token GitHub personal access token
         */
        public function authorize($token) {
            $this->authorize_token = $token;
        }

        /**
         * Get repository information from GitHub API
         * 
         * @return object|bool GitHub API response or false on failure
         */
        private function get_repository_info() {
            if (is_null($this->github_response)) {
                $request_uri = sprintf(
                    'https://api.github.com/repos/%s/%s/releases/latest',
                    $this->username,
                    $this->repository
                );

                $args = array(
                    'timeout' => 15,
                );

                // Add authorization header for private repos
                if ($this->authorize_token) {
                    $args['headers'] = array(
                        'Authorization' => 'token ' . $this->authorize_token,
                    );
                }

                $response = wp_remote_get($request_uri, $args);

                if (is_wp_error($response)) {
                    return false;
                }

                $response_code = wp_remote_retrieve_response_code($response);
                
                if ($response_code !== 200) {
                    return false;
                }

                $this->github_response = json_decode(wp_remote_retrieve_body($response));
            }

            return $this->github_response;
        }

        /**
         * Initialize the updater hooks
         */
        public function initialize() {
            add_filter('pre_set_site_transient_update_plugins', array($this, 'modify_transient'), 10, 1);
            add_filter('plugins_api', array($this, 'plugin_popup'), 10, 3);
            add_filter('upgrader_post_install', array($this, 'after_install'), 10, 3);
            add_filter('upgrader_source_selection', array($this, 'fix_source_folder'), 10, 3);
        }

        /**
         * Modify the WordPress plugin update transient
         * 
         * @param object $transient The update_plugins transient
         * @return object Modified transient
         */
        public function modify_transient($transient) {
            if (property_exists($transient, 'checked')) {
                if ($checked = $transient->checked) {
                    $this->get_repository_info();

                    if ($this->github_response) {
                        $version = $this->github_response->tag_name;
                        // Remove 'v' prefix if present
                        $version = ltrim($version, 'v');

                        $out_of_date = version_compare($version, $checked[$this->basename], 'gt');

                        if ($out_of_date) {
                            $new_files = $this->github_response->zipball_url;

                            if ($this->authorize_token) {
                                $new_files = add_query_arg(
                                    array('access_token' => $this->authorize_token),
                                    $new_files
                                );
                            }

                            $plugin = array(
                                'url' => $this->plugin['PluginURI'],
                                'slug' => current(explode('/', $this->basename)),
                                'package' => $new_files,
                                'new_version' => $version,
                            );

                            $transient->response[$this->basename] = (object) $plugin;
                        }
                    }
                }
            }

            return $transient;
        }

        /**
         * Modify the plugin information popup
         * 
         * @param object $result The result object
         * @param string $action The API action
         * @param object $args Arguments passed to the API
         * @return object Modified result
         */
        public function plugin_popup($result, $action, $args) {
            if ($action !== 'plugin_information') {
                return $result;
            }

            if (!empty($args->slug)) {
                if ($args->slug == current(explode('/', $this->basename))) {
                    $this->get_repository_info();

                    if ($this->github_response) {
                        $version = $this->github_response->tag_name;
                        $version = ltrim($version, 'v');

                        $plugin = array(
                            'name' => $this->plugin['Name'],
                            'slug' => $this->basename,
                            'version' => $version,
                            'author' => $this->plugin['AuthorName'],
                            'author_profile' => $this->plugin['AuthorURI'],
                            'last_updated' => $this->github_response->published_at,
                            'homepage' => $this->plugin['PluginURI'],
                            'short_description' => $this->plugin['Description'],
                            'sections' => array(
                                'Description' => $this->plugin['Description'],
                                'Updates' => $this->github_response->body,
                            ),
                            'download_link' => $this->github_response->zipball_url,
                        );

                        return (object) $plugin;
                    }
                }
            }

            return $result;
        }

        /**
         * Fix the source folder name after download
         * GitHub creates folders like username-repository-abc123
         * We need to rename to just the repository name
         * 
         * @param string $source File source location
         * @param string $remote_source Remote file source location
         * @param object $upgrader WP_Upgrader instance
         * @return string Modified source location
         */
        public function fix_source_folder($source, $remote_source, $upgrader) {
            global $wp_filesystem;

            // Check if we're dealing with this plugin
            if (strpos($source, $this->repository) === false) {
                return $source;
            }

            // GitHub creates a folder like username-repository-abc123
            // We need to rename it to just the repository name (or plugin folder name)
            $plugin_folder = dirname($this->basename);
            $new_source = trailingslashit($remote_source) . trailingslashit($plugin_folder);

            $wp_filesystem->move($source, $new_source);

            return trailingslashit($new_source);
        }

        /**
         * Perform actions after installation
         * 
         * @param object $response Installation response
         * @param array $hook_extra Extra arguments
         * @param object $result Installation result
         * @return object Modified result
         */
        public function after_install($response, $hook_extra, $result) {
            global $wp_filesystem;

            $install_directory = plugin_dir_path($this->file);
            $wp_filesystem->move($result['destination'], $install_directory);
            $result['destination'] = $install_directory;

            if ($this->active) {
                activate_plugin($this->basename);
            }

            return $result;
        }
    }
}