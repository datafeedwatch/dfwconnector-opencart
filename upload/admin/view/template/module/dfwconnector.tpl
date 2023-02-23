<?php
    echo $header;
    echo $column_left;
?>

<div id="content">
    <div class="page-header">
        <div class="container-fluid">
            <ul class="breadcrumb">
                <?php foreach ($breadcrumbs as $breadcrumb) { ?>
                <li><a href="<?php echo $breadcrumb['href']; ?>"><?php echo $breadcrumb['text']; ?></a></li>
                <?php } ?>
            </ul>
        </div>
    </div>
    <div class="container-fluid">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h1><img src="view/image/dfwconnector/logo.png" alt="" /> <?php echo $heading_title; ?></h1>
            </div>
            <div class="bridge">
                <div class="message"></div>

                <div class="container">
                    <div class="text-center" id="content-block"></div><br/>
                    <div class="text-center">

                        <div class="progress progress-dark progress-small progress-striped active">
                            <div class="progress-bar" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>

                        <div class="store-key">
                            <span class="store-key-title">Your store key:</span>
                            <input class="store-key-content" id="storeKey" title="Your store key" type="text" name="comment" size="40" value="<?php echo $store_key; ?>" readonly>
                            <button id="updateBridgeStoreKey" class="btn-update-store-key">Update Store Key</button>
                        </div>

                        <button id="btn-setup" class="<?php echo $setup_button_class;?> btn-setup"><?php echo $setup_button; ?></button>

                    </div>

                    <div class="clearfix"></div>
                </div>
            </div>
        </div>
    </div>
</div>
<script type="text/javascript">
    jQuery(document).ready(function($) {

        var SELF_PATH = '<?php echo $url;?>';
        var bridgeInstalledMsg = '<?php echo $bridge_installed_msg; ?>';
        var bridgeNotInstalledMsg = '<?php echo $bridge_not_installed_msg; ?>';
        var buttonInstallMsg = '<?php echo $setup_button_install; ?>';
        var buttonUninstallMsg = '<?php echo $setup_button_uninstall; ?>';
        var setupButton = $('.btn-setup');
        var contentBlockManage = $('#content-block');
        var bridgeStoreKey = $('#bridgeStoreKey');
        var storeKey = $('#storeKey');
        var storeBlock = $('.store-key');
        var classMessage = $('.message');
        var progress = $('.progress');

        var updateBridgeStoreKey = $('#updateBridgeStoreKey');

        if (storeKey.val() == '') {
            contentBlockManage.html(bridgeNotInstalledMsg);
            storeBlock.fadeOut();
            updateBridgeStoreKey.hide();
        } else {
            contentBlockManage.html(bridgeInstalledMsg);
            storeBlock.fadeIn();
            updateBridgeStoreKey.show();
        }

        function statusMessage(message, status) {
            if (status == 'success') {
                classMessage.removeClass('bridge_error');
            } else {
                classMessage.addClass('bridge_error');
            }
            classMessage.html('<span>' + message + '</span>');
            classMessage.fadeIn("slow");
            var messageClear = setTimeout(function() {
                classMessage.fadeOut(1000);
                clearTimeout(messageClear);
            }, 3000);
        }

        $('.btn-setup').click(function() {
            var self = $(this);
            $(this).attr("disabled", true);
            progress.slideDown("fast");
            var actionName = 'installBridge';
            if ($('.btn-setup').html() !== buttonInstallMsg) {
                actionName = 'unInstallBridge';
            }

            $.ajax({
                cache: false,
                method: 'POST',
                type:'POST',
                url: SELF_PATH,
                data: {action: actionName},
                dataType: 'json',
                error: function(jqXHR, textStatus) {
                    statusMessage('Can not update Connector: ' + textStatus  ,'error');
                    progress.fadeOut("fast");
                    $('.btn-setup').attr("disabled", false);
                },
                success: function(data) {
                    self.attr("disabled", false);
                    progress.slideUp("fast");
                    if (actionName == 'installBridge') {
                        if (data.error != null || data.result == false) {
                            statusMessage('Can not install Connector: ' + data.error, 'error');
                            return;
                        }
                        contentBlockManage.html(bridgeInstalledMsg);
                        updateStoreKey(data.result);
                        setupButton.html(buttonUninstallMsg);
                        setupButton.removeClass('btn-connect');
                        setupButton.addClass('btn-disconnect');

                        storeBlock.fadeIn("slow");
                        updateBridgeStoreKey.fadeIn("slow");
                        statusMessage('Connector Installed Successfully','success');
                    } else {
                        if (data.result != true) {
                            statusMessage('Can not uninstall Connector: ' + data.error,'error');
                            return;
                        }

                        setupButton.html(buttonInstallMsg);
                        setupButton.removeClass('btn-disconnect');
                        setupButton.addClass('btn-connect');

                        contentBlockManage.html(bridgeNotInstalledMsg);
                        storeBlock.fadeOut("fast");
                        updateBridgeStoreKey.fadeOut("fast");
                        statusMessage('Connector Uninstalled Successfully','success');
                    }
                }
            });
        });

        updateBridgeStoreKey.click(function() {
            $.ajax({
                cache: false,
                method: 'POST',
                type:'POST',
                url: SELF_PATH,
                data: {action: 'updateToken'},
                dataType: 'json',
                success: function(data) {
                    if (data.error != null || data.result == false) {
                        statusMessage('Can not update Connector:' + data.error,'error');
                        return;
                    }
                    updateStoreKey(data.result);
                    statusMessage('Store key updated successfully!','success');
                },
                error: function(jqXHR, textStatus) {
                    statusMessage('Can not update Connector: ' + textStatus  ,'error');
                }
            });
        });

        function updateStoreKey(store_key){
            storeKey.val(store_key);
        }
    });
</script>
<link rel="stylesheet" href="view/stylesheet/dfwconnector.css" type="text/css" media="all">