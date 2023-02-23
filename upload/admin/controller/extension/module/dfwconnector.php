<?php

class ControllerExtensionModuleDfwconnector extends Controller
{

  private $_error = null;
  private $_rootDir;
  private $_bridgeDownloadUrl = 'https://api.api2cart.com/v1.0/bridge.download.file?whitelabel=true';

  public function __construct($registry)
  {
    parent::__construct($registry);
    $this->_rootDir = realpath(DIR_APPLICATION . '../');
    $this->language->load('extension/module/dfwconnector');
    $this->load->model('setting/setting');
  }

  protected function _handleRequest()
  {
    $result = null;

    if (!isset($this->request->post['action'])
      || !in_array($this->request->post['action'], ['installBridge', 'unInstallBridge', 'updateToken'])
    ) {
      $this->_error = $this->language->get('error_invalid_action');
    }

    if ($this->_error === null) {
      switch ($this->request->post['action']) {
        case 'installBridge':
          $result = $this->_installBridge();
          if ($result) {
            $this->_updateModuleStatus(1);
          }
          break;
        case 'unInstallBridge':
          $result = $this->_unInstallBridge();
          if ($result) {
            $this->_updateModuleStatus(0);
          }
          break;
        case 'updateToken':
          $token = $this->_generateStoreKey();
          $result = $this->_updateToken($token) ? $token : false;
          break;
      }
    }

    $this->response->setOutput(
      json_encode(
        [
          'error' => $this->_error,
          'result' => $result
        ]
      )
    );
  }

  private function _updateModuleStatus($newStatus)
  {
    $this->model_setting_setting->editSetting('module_dfwconnector', array('module_dfwconnector_status' => $newStatus));
    $this->config->set('module_dfwconnector_status', $newStatus);
  }

  public function index()
  {
    if (($this->request->server['REQUEST_METHOD'] == 'POST')) {
      if (!$this->user->hasPermission('modify', 'extension/module/dfwconnector')) {
        $this->_error = $this->language->get('error_permission');
      }

      $this->_handleRequest();
    } else {
      $this->document->setTitle($this->language->get('heading_title'));
      $this->load->model('setting/setting');


      $data['heading_title'] = $this->language->get('heading_title');
      $data['url'] = html_entity_decode($this->url->link('extension/module/dfwconnector', 'user_token=' . $this->session->data['user_token'], true));

      if (isset($this->error['warning'])) {
        $data['error_warning'] = $this->error['warning'];
      } else {
        $data['error_warning'] = '';
      }

      if (isset($this->error['code'])) {
        $data['error_code'] = $this->error['code'];
      } else {
        $data['error_code'] = '';
      }

      $data['breadcrumbs'] = array();

      $data['breadcrumbs'] = array();

      $data['breadcrumbs'][] = array(
        'text' => $this->language->get('text_home'),
        'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
      );

      $data['breadcrumbs'][] = array(
        'text' => $this->language->get('extension'),
        'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
      );

      $data['breadcrumbs'][] = array(
        'text' => $this->language->get('heading_title'),
        'href' => $this->url->link('extension/module/dfwconnector', 'user_token=' . $this->session->data['user_token'], true)
      );

      if ($this->_isBridgeInstalled() === 3) {
        $data['store_key'] = $this->_getCurrentStoreKey();
        $data['setup_button'] = $this->language->get('setup_button_uninstall');
        $data['setup_button_class'] = 'btn-disconnect';
        $data['module_dfwconnector_status'] = 1;
        $this->_updateModuleStatus(1);
      } else {
        $data['store_key'] = '';
        $data['setup_button'] = $this->language->get('setup_button_install');
        $data['setup_button_class'] = 'btn-connect';
        $data['module_dfwconnector_status'] = 0;
        $this->_updateModuleStatus(0);
      }

      $data['bridge_installed_msg'] = $this->language->get('bridge_installed_msg');
      $data['bridge_not_installed_msg'] = $this->language->get('bridge_not_installed_msg');
      $data['setup_button_uninstall'] = $this->language->get('setup_button_uninstall');
      $data['setup_button_install'] = $this->language->get('setup_button_install');
      $data['header'] = $this->load->controller('common/header');
      $data['column_left'] = $this->load->controller('common/column_left');

      $this->response->setOutput($this->load->view('extension/module/dfwconnector', $data));
    }
  }

  private function _isBridgeInstalled()
  {
    $status = 0;
    if (is_dir($this->_rootDir . '/bridge2cart')) {
      $status++;
    }

    if (file_exists($this->_rootDir . '/bridge2cart/bridge.php')) {
      $status++;
    }

    if (file_exists($this->_rootDir . '/bridge2cart/config.php')) {
      $status++;
    }

    return $status;
  }

  protected function _getCurrentStoreKey()
  {
    include $this->_rootDir . '/bridge2cart/config.php';
    return M1_TOKEN;
  }

  /**
   * @return bool
   */
  private function _installBridge()
  {
    $status = $this->_isBridgeInstalled();
    if ($status === 3) {
      return $this->_getCurrentStoreKey();
    } elseif ($status > 0) {
      if (!$this->_unInstallBridge()) {
        return false;
      }
    }

    if (!is_writable($this->_rootDir)) {
      $this->_error = $this->_rootDir . ' ' . $this->language->get('error_entity_is_not_writable');
      return false;
    }

    $bridgeArchive = $this->_rootDir . DIRECTORY_SEPARATOR . 'bridge.zip';
    $status = file_put_contents($bridgeArchive, $this->_getContent($this->_bridgeDownloadUrl));
    if (!$status) {
      return false;
    }

    $zip = new ZipArchive;
    $res = $zip->open($bridgeArchive);

    if ($res === true) {
      $zip->deleteName('readme.txt');
      $zip->close();

      $zip->open($bridgeArchive);
      $zip->extractTo($this->_rootDir . DIRECTORY_SEPARATOR);
      $zip->close();
    }

    unlink($bridgeArchive);

    if ($res) {
      $storeKey = $this->_generateStoreKey();
      $this->_updateToken($storeKey);

      return $storeKey;
    }

    return false;
  }

  /**
   * @return bool
   */
  private function _unInstallBridge()
  {
    if (!is_dir($this->_rootDir . '/bridge2cart')) {
      return true;
    }

    return $this->_deleteDir($this->_rootDir . '/bridge2cart');
  }

  /**
   * @param $token
   *
   * @return bool
   */
  private function _updateToken($token)
  {
    $path = $this->_rootDir . '/bridge2cart/config.php';
    if (!is_writable($path)) {
      $this->_error = $path . ' ' . $this->language->get('error_entity_is_not_writable');
      return false;
    }

    $config = @fopen($path, 'w');

    if (!$config) {
      return false;
    }

    $writed = fwrite($config, "<?php define('M1_TOKEN', '" . $token . "');");
    if ($writed === false) {
      return false;
    }

    fclose($config);
    return true;
  }

  /**
   * @param $dirPath
   *
   * @return bool
   */
  private function _deleteDir($dirPath)
  {
    if (is_dir($dirPath)) {
      if (!is_writable($dirPath)) {
        $this->_error = $dirPath . ' ' . $this->language->get('error_entity_is_not_writable');
        return false;
      }

      $objects = scandir($dirPath);

      foreach ($objects as $object) {
        if ($object != "." && $object != "..") {
          if (!is_writable($dirPath  . DIRECTORY_SEPARATOR . $object)) {
            $this->_error = $dirPath . DIRECTORY_SEPARATOR . $object . ' ' . $this->language->get('error_entity_is_not_writable');
            return false;
          }
          if (filetype($dirPath . DIRECTORY_SEPARATOR . $object) == "dir") {
            $this->_deleteDir($dirPath . DIRECTORY_SEPARATOR . $object);
          } elseif (!unlink($dirPath . DIRECTORY_SEPARATOR . $object)) {
            return false;
          }
        }
      }

      reset($objects);

      if (!rmdir($dirPath)) {
        return false;
      }
    } else {
      return false;
    }

    return true;
  }

  /**
   * @return string
   */
  protected function _generateStoreKey()
  {
    $bytesLength = 256;

    if (function_exists('random_bytes')) { // available in PHP 7
      return md5(random_bytes($bytesLength));
    }

    if (function_exists('mcrypt_create_iv')) {
      $bytes = mcrypt_create_iv($bytesLength, MCRYPT_DEV_URANDOM);
      if ($bytes !== false && strlen($bytes) === $bytesLength) {
        return md5($bytes);
      }
    }

    if (function_exists('openssl_random_pseudo_bytes')) {
      $bytes = openssl_random_pseudo_bytes($bytesLength);
      if ($bytes !== false) {
        return md5($bytes);
      }
    }

    if (file_exists('/dev/urandom') && is_readable('/dev/urandom')) {
      $frandom = fopen('/dev/urandom', 'r');
      if ($frandom !== false) {
        return md5(fread($frandom, $bytesLength));
      }
    }

    $rand = '';
    for ($i = 0; $i < $bytesLength; $i++) {
      $rand .= chr(mt_rand(0, 255));
    }

    return md5($rand);
  }

  /**
   * @param $url
   * @param int $timeout
   * @return bool|mixed|string
   */
  private function _getContent($url, $timeout = 5)
  {
    if (in_array(ini_get('allow_url_fopen'), array('On', 'on', '1'))) {
      return @file_get_contents($url);
    } elseif (function_exists('curl_init')) {
      $curl = curl_init();
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($curl, CURLOPT_URL, $url);
      curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
      curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
      curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
      $content = curl_exec($curl);
      curl_close($curl);
      return $content;
    } else {
      return false;
    }
  }

  public function install()
  {
    if ($this->_installBridge()) {
      $this->_updateModuleStatus(1);
    }
  }

  public function uninstall()
  {
    if ($this->_unInstallBridge()) {
      $this->_updateModuleStatus(0);
    }
  }

}