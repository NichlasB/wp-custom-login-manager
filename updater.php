<?php
if (!class_exists('WP_GitHub_Updater')) {
    class WP_GitHub_Updater {
        private $slug;
        private $plugin;
        private $api_url;
        private $github_url;
        private $zip_url;
        private $version;

        public function __construct($config = array()) {
            $this->initialize($config);
            $this->set_defaults();
            $this->hooks();
        }

        private function initialize($config) {
            $this->slug = isset($config['slug']) ? $config['slug'] : basename(dirname(__FILE__));
            $this->plugin = plugin_basename(dirname(__FILE__) . '/' . $this->slug . '.php');
            $this->api_url = isset($config['api_url']) ? $config['api_url'] : '';
            $this->github_url = isset($config['github_url']) ? $config['github_url'] : '';
            $this->zip_url = isset($config['zip_url']) ? $config['zip_url'] : '';
            $this->version = isset($config['version']) ? $config['version'] : '';
        }

        private function set_defaults() {
            if (empty($this->api_url)) {
                $path = trim(parse_url($this->github_url, PHP_URL_PATH), '/');
                list($username, $repo) = explode('/', $path);
                $this->api_url = sprintf('https://api.github.com/repos/%s/%s/releases/latest',
                    $username,
                    $repo
                );
            }
            if (empty($this->zip_url)) {
                $path = trim(parse_url($this->github_url, PHP_URL_PATH), '/');
                $this->zip_url = sprintf('https://github.com/%s/archive/refs/tags/',
                    $path
                );
            }
        }

        private function hooks() {
            add_filter('pre_set_site_transient_update_plugins', array($this, 'check_update'));
            add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
            add_filter('upgrader_source_selection', array($this, 'upgrader_source_selection'), 10, 4);
        }

        public function check_update($transient) {
            if (empty($transient->checked)) {
                return $transient;
            }

            $remote_version = $this->get_remote_version();
            if ($remote_version && version_compare($this->version, $remote_version, '<')) {
                $response = new stdClass();
                $response->slug = $this->slug;
                $response->plugin = $this->plugin;
                $response->new_version = $remote_version;
                $response->url = $this->github_url;
                $response->package = $this->zip_url . 'v' . $remote_version . '.zip';
                $transient->response[$this->plugin] = $response;
            }

            return $transient;
        }

        private function get_remote_version() {
            $request = wp_remote_get($this->api_url);
            if (!is_wp_error($request)) {
                $body = wp_remote_retrieve_body($request);
                $data = json_decode($body);
                if (isset($data->tag_name)) {
                    return ltrim($data->tag_name, 'v');
                }
            }
            return false;
        }

        public function plugin_info($result, $action, $args) {
            if ('plugin_information' !== $action) {
                return $result;
            }

            if ($this->slug !== $args->slug) {
                return $result;
            }

            $request = wp_remote_get($this->api_url);
            if (!is_wp_error($request)) {
                $response = json_decode(wp_remote_retrieve_body($request));
                if ($response) {
                    $info = new stdClass();
                    $info->name = $this->slug;
                    $info->slug = $this->slug;
                    $info->version = ltrim($response->tag_name, 'v');
                    $info->author = '';
                    $info->homepage = $this->github_url;
                    $info->requires = '5.0';
                    $info->tested = '6.4';
                    $info->downloaded = 0;
                    $info->last_updated = $response->published_at;
                    $info->sections = array(
                        'description' => $response->body,
                        'changelog' => $response->body
                    );
                    $info->download_link = $this->zip_url . 'v' . $info->version . '.zip';
                    return $info;
                }
            }
            return $result;
        }

        public function upgrader_source_selection($source, $remote_source, $upgrader, $hook_extra) {
            if (isset($hook_extra['plugin']) && $hook_extra['plugin'] === $this->plugin) {
                $source_files = scandir($source);
                if ($source_files) {
                    $temp_source = trailingslashit($remote_source) . trailingslashit($this->slug);
                    rename($source, $temp_source);
                    return $temp_source;
                }
            }
            return $source;
        }
    }
}