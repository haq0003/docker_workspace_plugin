<?php

class docker_workspace_plugin
{

    var $plugin_name = 'docker_workspace_plugin';
    var $class_name = 'docker_workspace_plugin';
    var $min_uid = 499;
    var $src_lemp_compose_folder = 'lemp-compose';
    var $src_lemp_compose_dir = '/home/haq0003/lemp-compose';

    /*
        This function is called when the plugin is loaded
    */

    function onLoad()
    {
        global $app;
        //Register for the events
        //var_dump("load");
        $app->plugins->registerEvent('shell_user_insert', $this->plugin_name, 'insert');
        $app->plugins->registerEvent('shell_user_update', $this->plugin_name, 'update');
    }

    //* This function is called, when a shell user is inserted in the database
    function insert($event_name, $data)
    {
        global $app, $conf;
        //var_dump("insert");
        $app->log($event_name, LOGLEVEL_DEBUG);
        $this->dockerizeSpace($app, $event_name, $data, $conf);

    }

    function update($event_name, $data)
    {
        global $app, $conf;
        //var_dump("update");
        $this->dockerizeSpace($app, $event_name, $data, $conf);

    }

    function dockerizeSpace($app, $event_name, $data, $conf)
    {
        // $web_config = $app->getconf->get_server_config($conf['server_id'], 'web');

        $web = $app->db->queryOneRecord("SELECT * FROM web_domain WHERE domain_id = ?", $data['new']['parent_domain_id']);

        $app->log("ADD Docker workspace", LOGLEVEL_DEBUG);
        if ($app->system->is_user($data['new']['puser'])) {
            $uid = intval($app->system->getuid($data['new']['puser']));

            if ($uid <= $this->min_uid) {
                $app->log("UID = $uid for shelluser:" . $data['new']['username'] . " not allowed.", LOGLEVEL_ERROR);

                return;
            }
            /**
             * Setup Jailkit Chroot System If Enabled
             */

            if ($data['new']['chroot'] == "jailkit") {
                $horodate = date('ymd-His');
                $app->log("Docker workspace exist ?" . print_r($data['new'], true), LOGLEVEL_DEBUG);

                $lemp_compose_dir = $data['new']['dir'];
                if (!preg_match('#client#', $lemp_compose_dir)) {
                    $app->log("Pb with dest folder $lemp_compose_dir", LOGLEVEL_ERROR);
                    return;
                }
                // debug on écrase toujours lemp
                if (true) {
                    $lemp_compose_user_dir = "$lemp_compose_dir/lemp-comp-{$data['new']['username']}";

                    // si le dossier existe alors on archive dans /var/backup
                    $command = '';

                    if (is_dir("$lemp_compose_dir/lemp-comp-{$data['new']['username']}")) {
                        $command .= " mkdir -p /var/backup/{$data['new']['puser']} ";
                        $command .= " && cd $lemp_compose_dir ";
                        $command .= " && zip -r /var/backup/{$data['new']['puser']}/lemp-comp-{$data['new']['username']}-$horodate.zip lemp-comp-{$data['new']['username']}/. -5 ";
                        $command .= " && rm -Rf $lemp_compose_dir/lemp-comp-{$data['new']['username']}/* ";
                    }

                    if ($command) {
                        $command .= " && ";
                    }

                    $command .= " chattr -i $lemp_compose_dir ";
                    $command .= " && mkdir -p $lemp_compose_dir/lemp-comp-{$data['new']['username']}/ ";
                    $command .= " && cp -r {$this->src_lemp_compose_dir}/* $lemp_compose_user_dir/. ";
                    $command .= " && touch $lemp_compose_user_dir/.README ";
                    $command .= " && chown -Rf {$data['new']['puser']}:{$data['new']['pgroup']} $lemp_compose_user_dir ";
                    $command .= " && find $lemp_compose_user_dir -type d -exec chmod 775 {} \; && find $lemp_compose_user_dir -type f -exec chmod 777 {} \; ";
                    if(is_file("$lemp_compose_user_dir/docker-compose.yml")){
                        $command .= " && rm $lemp_compose_user_dir/docker-compose.yml ";
                    }
                    $command .= " && chattr +i $lemp_compose_dir ";
                    $command .= " && usermod -a -G docker {$data['new']['puser']} ";


                    exec($command);

                    // on modifie docker-compose.yml
                    $docker_compose = file_get_contents($lemp_compose_user_dir . '/docker-compose.yml.tpl');

                    $data_map = array(
                        '[[WEB_ID]]' => $data['new']['parent_domain_id'],
                        '[[USER_ID]]' => $data['new']['shell_user_id'],
                        '[[ROOT_PASS]]' => 'MYROOTPASSWD',
                        '[[SUFF_PASS]]' => 'MYSUFFPASSWD',
                    );
                    $data_map['[[PUID]]'] = intval($app->system->getuid($data['new']['puser']));
                    $data_map['[[GUID]]'] = intval($app->system->getgid($data['new']['pgroup']));
                    $data_map['[[PGGROUP]]'] = $data['new']['pgroup'];
                    $data_map['[[PUSER]]'] = $data['new']['puser'];
                    $data_map['[[USERNAME]]'] = $data['new']['username'];
                    $data_map['[[DOMAIN]]'] = $web['domain'];

                    $docker_compose = str_replace(array_keys($data_map), array_values($data_map), $docker_compose);

                    file_put_contents("$lemp_compose_user_dir/docker-compose.yml", $docker_compose);

                    // on modifie dockerfile de workspace
                    $workspace_dockerfile = file_get_contents($lemp_compose_user_dir . '/workspace/Dockerfile.tpl');
                    $workspace_dockerfile = str_replace(array_keys($data_map), array_values($data_map), $workspace_dockerfile);

                    file_put_contents("$lemp_compose_user_dir/workspace/Dockerfile", $workspace_dockerfile);

                    // on modifie dockerfile de la conf nginx
                    $web_nginx_app_conf = file_get_contents($lemp_compose_user_dir . '/web/nginx/app.conf.tpl');
                    $web_nginx_app_conf = str_replace(array_keys($data_map), array_values($data_map), $web_nginx_app_conf);

                    file_put_contents($lemp_compose_user_dir . '/web/nginx/app.conf', $web_nginx_app_conf);

                    $web_apache_app_conf = file_get_contents($lemp_compose_user_dir . '/web/apache2/app.conf.tpl');
                    $web_apache_app_conf = str_replace(array_keys($data_map), array_values($data_map), $web_apache_app_conf);

                    file_put_contents($lemp_compose_user_dir . '/web/apache2/app.conf', $web_apache_app_conf);


                    $command = "rm  $lemp_compose_user_dir/docker-compose.yml.tpl ";
                    $command .= " && rm  $lemp_compose_user_dir/workspace/Dockerfile.tpl ";
                    $command .= " && rm  $lemp_compose_user_dir/web/nginx/app.conf.tpl ";

                    exec($command);

                    // Fiche avec les informations

                    $informations_connexion = <<<INF


/** FRONT **/
HOST    : {$data_map['[[DOMAIN]]']}:{$data_map['[[USER_ID]]']}80

/** SSH **/
HOST    : {$data_map['[[DOMAIN]]']}
PORT    : {$data_map['[[USER_ID]]']}22
LOGIN   : {$data_map['[[PUSER]]']}
PASSWD  : {$data_map['[[USERNAME]]']}{$data_map['[[SUFF_PASS]]']} 
ROOTPWD : {$data_map['[[ROOT_PASS]]']}
PATH    : /var/www

COMMAND : ssh -p {$data_map['[[USER_ID]]']}22 {$data_map['[[PUSER]]']}@{$data_map['[[DOMAIN]]']}
PASS : {$data_map['[[USERNAME]]']}{$data_map['[[SUFF_PASS]]']} 



/** PMA **/
HOST    : http://{$data_map['[[DOMAIN]]']}:{$data_map['[[USER_ID]]']}81
LOGIN   : root
PASSWD  : {$data_map['[[ROOT_PASS]]']}

/** MYSQL **/

mysql -uroot -p"{$data_map['[[ROOT_PASS]]']}" -h mysql

/** COMMAND **/

First build : docker-compose up --force-recreate --build -d

other Usefull commands : 
  docker-compose log
  docker-compose ps
  docker-compose  down
  docker-compose  stop
  docker-compose  kill
  docker-compose  up -d


INF;

                    file_put_contents("$lemp_compose_user_dir/.README", $informations_connexion);


                }


            }

        }

    }


}
