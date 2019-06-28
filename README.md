Buil Developpement Environnement with docker and ispconfig

1 / Install ispconfig "google : howtoforge ispconfig" with Jailkit

2 / Install docker compose

    apt-get install -y docker.io
    apt-get install -y python python-pip
    pip install docker-compose

3/ Create user for docker 

    useradd -m -s /bin/bash haq0003
    usermod -a -G docker haq0003
    
4/ Download model lemp-compose with template

    su -- haq0003
    cd ~
    git clone https://github.com/haq0003/lemp-compose.git

5/ Add plugin 
 
    cd /usr/local/ispconfig/server/plugins-available/
    wget https://raw.githubusercontent.com/haq0003/docker_workspace_plugin/master/docker_workspace_plugin.inc.php
    
    Replace 'MYSUFFPASSWD' and 'MYROOTPASSWD'
    
    cd /usr/local/ispconfig/server/plugins-enabled
    ln ../plugins-available/docker_workspace_plugin.inc.php
    
6/ Create website with ispconfig

7/ Create ssh user with option "Jailkit"

8/ Build machine

   su -- haq0003
   cd /var/www/website.com/lemp-dir-username
   cat .README





    
