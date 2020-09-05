<?php

$version = '2.20';


$tabOpcache = null;
$tabStatus = null;

try{
    $tabOpcache = opcache_get_configuration();
    $tabStatus = opcache_get_status();
}catch(Error $e){}

$function = function()
{
    $path = 'Tests/reports/FullTest/index.html';

    if (file_exists($path)) {
        return $path;
    }
};


$fileIndex = $function();
?>
<link rel="stylesheet" href="/system/bootstrap-toolbar.css">
<style type="text/css">


</style>
<div id="nm_toolbar">
    <div class="nm_container-fluid nm_fixed-bottom" style="background: #333;">
        <button class="nm_btn nm_noir nm_no-radius nm_no-cursor">Dev</button>
        <div class="nm_btn-group nm_dropup">
            <button type="button" class="nm_btn nm_rouge nm_no-radius" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <?php echo (string) basename($controller).' - '.(string) $method.' - '.$action; ?>
            </button>
        </div>
        <?php if ($tabOpcache !== null && isset($tabOpcache['directives']['opcache.enable']) && $tabOpcache['directives']['opcache.enable']) {
            ?>
            <div class="nm_btn-group nm_dropup">
                <button type="button" class="nm_btn nm_noir nm_dropdown-toggle nm_no-radius" data-toggle="nm_dropdown" aria-haspopup="true" aria-expanded="false">
                    OpCache <?php echo 'enable'; ?>
                </button>
                <div class="nm_dropdown-menu">
                    <button class="nm_btn nm_dropdown-item nm_vert" style="cursor: pointer;" data-toggle="nm_modal" data-target="#cache">Dashboard</button>
                    <form method="post" action="<?php echo $_GET['p']; ?>">
                        <input name="resetCache" type="hidden">
                        <input type="submit" value="Vider le cache" class="nm_btn nm_dropdown-item nm_rouge">
                    </form>
                </div>
            </div>
            <?php
        } else {
            ?>
            <button type="button" class="nm_btn nm_nm_bleu-fonce nm_no-radius" >OpCache disable</button>

            <?php
        }?>
        <?php if (@ini_get_all('xdebug')['xdebug.remote_enable']['local_value'] === '1') {
            ?>
            <!--  --><div class="nm_btn-group nm_dropup">
                <button type="button" class="nm_btn nm_vert nm_dropdown-toggle nm_no-radius" data-toggle="nm_dropdown" aria-haspopup="true" aria-expanded="false">
                    xDebug enable
                </button>
                <div class="nm_dropdown-menu">
                    <button class="nm_btn nm_dropdown-item nm_noir" style="color: white; cursor: pointer;" data-toggle="nm_modal" data-target="#xdebug">Config</button>
                    <?php if (ini_get_all('xdebug')['xdebug.profiler_enable']['local_value'] === '1') {
                        ?>
                        <button class="nm_btn nm_dropdown-item nm_vert nm_no-cursor nm_no-radius">Profiler</button>
                        <?php
                    } else {
                        ?>
                        <button class="nm_btn nm_dropdown-item nm_rouge nm_no-cursor nm_no-radius">Profiler</button>
                        <?php
                    } ?>
                    <button class="nm_btn nm_dropdown-item nm_vert nm_no-cursor nm_no-radius">Debug</button>
                </div>
            </div>
            <?php
        } else {
            ?>
            <button class="nm_btn nm_nm_bleu-fonce nm_no-cursor">xDebug disable</button>
            <?php
        }?>

        <button class="nm_btn nm_rouge nm_no-radius">Time: <?php echo number_format($time, 3, '.', '') ?> sec</button>
        <div class="nm_btn-group nm_dropup">
            <button type="button" class="nm_btn nm_bleu nm_dropdown-toggle nm_no-radius" data-toggle="nm_dropdown" aria-haspopup="true" aria-expanded="false">
                PHPUnit
            </button>
            <div class="nm_dropdown-menu">

                <?php
                if($fileIndex === null){
                    echo '<button class="nm_btn nm_dropdown-item nm_vert nm_no-cursor">No report found</button>';
                }else{
                    echo '<button class="nm_btn nm_dropdown-item nm_vert" style="cursor: pointer;" data-toggle="nm_modal" data-target="#report">FullTest</button>';
                }
                ?>
            </div>
        </div>
        <div style="float: right">
            <div class="nm_btn-group nm_dropup">
                <button type="button" class="nm_btn nm_vert nm_dropdown-toggle nm_no-radius" data-toggle="nm_dropdown" aria-haspopup="true" aria-expanded="false">
                    Mods & Lib
                </button>
                <div class="nm_dropdown-menu">
                    <?php
                    try{
                        if (@ini_get_all('xdebug')['xdebug.remote_enable']['local_value'] === '1') {
                            ?>
                            <button class="nm_btn nm_dropdown-item nm_vert nm_no-cursor">xDebug</button>
                            <?php
                        } else {
                            ?>
                            <button class="nm_btn nm_dropdown-item nm_rouge nm_no-cursor">xDebug</button>
                            <?php
                        }
                        if ($tabOpcache !== null && $tabOpcache['directives']['opcache.enable'] === true) {
                            ?>
                            <button class="nm_btn nm_dropdown-item nm_vert nm_no-cursor">OpCache</button>
                            <?php
                        } else {
                            ?>
                            <button class="nm_btn nm_dropdown-item nm_rouge nm_no-cursor">OpCache</button>
                            <?php
                        }
                    }catch(Throwable $e){}
                    ?>
                    <button class="nm_btn nm_dropdown-item nm_bleu nm_no-cursor">Twig 3.0</button>
                    <button class="nm_btn nm_dropdown-item nm_bleu nm_no-cursor">PHPUnit.8.5</button>
                    <button class="nm_btn nm_dropdown-item nm_bleu nm_no-cursor">PHP-DI 6</button>
                    <button class="nm_btn nm_dropdown-item nm_bleu nm_no-cursor">PHP-REF</button>
                </div>
            </div>
            <button class="nm_btn nm_bleu nm_no-radius">NoMess.<?php echo $version ?></button>
        </div>
    </div>

    <!-- Button trigger nm_modal -->
    <?php
    try{
        if ($tabOpcache !== null && $tabOpcache['directives']['opcache.enable']) {
            ?>
            <div class="nm_modal fade" id="cache" tabindex="-1" role="dialog" aria-labelledby="examplenm_modalLabel" aria-hidden="true">
                <div class="nm_modal-dialog nm_modal-lg" role="document">
                    <div class="nm_modal-content nm_no-radius">
                        <div class="nm_modal-header nm_rouge nm_no-radius">
                            <h5 class="nm_modal-title" id="examplenm_modalLabel">OpCache</h5>
                            <button type="button" class="close" data-dismiss="nm_modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="nm_modal-body">
                            <div class="nm_container">
                                <ul class="nav nav-tabs" id="myTab" role="tablist">
                                    <li class="nav-item">
                                        <a class="nm_a nav-link active" style="color: #333;" id="home-tab" data-toggle="tab" href="#stat" role="tab" aria-controls="home" aria-selected="true">Statistiques</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nm_a nav-link" style="color: #333;" id="profile-tab" data-toggle="tab" href="#profile" role="tab" aria-controls="profile" aria-selected="false">Cache</a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nm_a nav-link" style="color: #333;" id="contact-tab" data-toggle="tab" href="#contact" role="tab" aria-controls="contact" aria-selected="false">Configuration</a>
                                    </li>
                                </ul>
                                <div class="tab-content" id="myTabContent">
                                    <div class="tab-pane fade show active" style="color: #333;" id="stat" role="tabpanel" aria-labelledby="home-tab">
                                        <table class="nm_table nm_table-dark">
                                            <thead>
                                            <tr>
                                                <th class="text-center" colspan="2" style="border-right: none"></th>
                                                <th style="border-left: none">Configurations</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <tr>
                                                <th>Scripts en cache</th>
                                                <td><?php echo $tabStatus['opcache_statistics']['num_cached_scripts']; ?> - (<?php echo number_format(((100 * $tabStatus['opcache_statistics']['num_cached_scripts']) / $tabOpcache['directives']['opcache.max_accelerated_files']), 2, ',', ''); ?>%)</td>
                                                <td><?php echo $tabOpcache['directives']['opcache.max_accelerated_files']; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Clef en cache</th>
                                                <td><?php echo $tabStatus['opcache_statistics']['num_cached_keys']; ?> - (<?php echo number_format(((100 * $tabStatus['opcache_statistics']['num_cached_keys']) / $tabStatus['opcache_statistics']['max_cached_keys']), 2, ',', ''); ?>%)</td>
                                                <td>/<?php echo $tabStatus['opcache_statistics']['max_cached_keys']; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Mémoire utilisé</th>
                                                <td><?php echo $tabStatus['memory_usage']['used_memory']; ?> - (<?php echo number_format(((100 * $tabStatus['memory_usage']['used_memory']) / ($tabStatus['memory_usage']['free_memory'] + $tabStatus['memory_usage']['used_memory'])), 2, ',', ''); ?>%)</td>
                                                <td>/<?php echo $tabStatus['memory_usage']['free_memory'] + $tabStatus['memory_usage']['used_memory']; ?></td>
                                            </tr>
                                            <tr>
                                                <th>Clef en cache</th>
                                                <td><?php echo $tabStatus['opcache_statistics']['num_cached_keys']; ?></td>
                                                <td>/<?php echo $tabStatus['opcache_statistics']['max_cached_keys']; ?></td>
                                            </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="tab-pane fade" id="profile" role="tabpanel" aria-labelledby="profile-tab">
                                        <table class="nm_table nm_table-dark">
                                            <thead>
                                            <tr>
                                                <th>Path System</th>
                                                <th>Invalidate</th>
                                            </tr>
                                            </thead>
                                            <tbody>
                                            <?php
                                            foreach ($tabStatus['scripts'] as $key => $value) {
                                                echo '
											<tr>
												<th>'.$key.'</th>
												<td>
													<form method="post" action="' . $_GET['p'] . '">
														<input type="hidden" name="invalide" value="'.$key.'">
														<input type="submit" class="nm_btn nm_btn-sm nm_no-radius nm_rouge" value="Invalider">
													</form>
												</td>
											</tr>
											';
                                            } ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="tab-pane fade" id="contact" role="tabpanel" aria-labelledby="contact-tab">
                                        <table class="nm_table nm_table-dark">
                                            <tbody>
                                            <?php
                                            foreach ($tabOpcache['directives'] as $key => $value) {
                                                if ($value === false) {
                                                    echo '
											<tr>
												<th>'.$key.'</th>
												<td>Off</td>
											</tr>
											';
                                                } elseif ($value === true) {
                                                    echo '
											<tr>
												<th>'.$key.'</th>
												<td>On</td>
											</tr>
											';
                                                } else {
                                                    echo '
											<tr>
												<th>'.$key.'</th>
												<td>'.$value.'</td>
											</tr>
											';
                                                }
                                            } ?>

                                            <tr>
                                                <th>blacklist</th>
                                                <td>
                                                    <?php
                                                    foreach ($tabOpcache['blacklist'] as $value) {
                                                        echo $value.'<br>';
                                                    } ?>
                                                </td>
                                            </tr>
                                            <?php

                                            foreach ($tabOpcache['version'] as $key => $value) {
                                                echo '
										<tr>
											<th>'.$key.'</th>
											<td>'.$value.'</td>
										</tr>
										';
                                            } ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="nm_modal-footer">
                            <button type="button" class="nm_btn nm_btn-secondary nm_no-radius" data-dismiss="nm_modal">Fermer</button>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }
    }catch(Throwable $e){}
    if (@ini_get_all('xdebug')['xdebug.remote_enable']['local_value'] === '1') {
        ?>
        <div class="nm_modal fade" id="xdebug" tabindex="-1" role="dialog" aria-labelledby="examplenm_modalLabel" aria-hidden="true">
            <div class="nm_modal-dialog nm_modal-lg" role="nm_document">
                <div class="nm_modal-content nm_no-radius">
                    <div class="nm_modal-header nm_rouge nm_no-radius">
                        <h5 class="h5 nm_h5 nm_modal-title" id="examplenm_modalLabel">xDebug</h5>
                        <button type="button" class="nm_close" data-dismiss="nm_modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="nm_modal-body">
                        <div class="nm_container">
                            <div class="nm_tab-content" id="myTabContent">
                                <div class="nm_tab-pane fade show nm_active" style="color: #333;" id="stat" role="nm_tabpanel" aria-labelledby="home-tab">
                                    <table class="nm_table nm_table-dark">
                                        <thead>
                                        <tr>
                                            <th class="nm_text-center">Directive</th>
                                            <th style="border-left: none">Local</th>
                                            <th style="border-left: none">Master</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php
                                        foreach (ini_get_all('xdebug') as $key => $value) {
                                            ?>
                                            <tr>
                                                <th><?php echo $key; ?></th>
                                                <td><?php echo $value['local_value']; ?></td>
                                                <td><?php echo $value['global_value']; ?></td>
                                            </tr>
                                            <?php
                                        } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="nm_modal-footer">
                        <button type="button" class="nm_btn nm_btn-secondary nm_no-radius" data-dismiss="nm_modal">Fermer</button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    ?>
    <div class="nm_modal fade" id="report" tabindex="-1" role="dialog" aria-labelledby="examplenm_modalLabel" aria-hidden="true">
        <div class="nm_modal-dialog nm_modal-lg" role="nm_document">
            <div class="nm_modal-content nm_no-radius">
                <div class="nm_modal-header nm_rouge nm_no-radius">
                    <h5 class="h5 nm_h5 nm_modal-title" id="examplenm_modalLabel">Coverage</h5>
                    <button type="button" class="nm_close" data-dismiss="nm_modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                
                <div class="nm_modal-footer">
                    <button type="button" class="nm_btn nm_btn-secondary nm_no-radius" data-dismiss="nm_modal">Fermer</button>
                </div>
            </div>
        </div>
    </div>
</div>
<!--<script type="text/javascript" src="/system/jquery.js"></script>-->
<script type="text/javascript" src="/system/popper.js"></script>
<script type="text/javascript" src="/system/bootstrap-toolbar.js"></script>
