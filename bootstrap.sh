#!/usr/bin/env bash
export DEBIAN_FRONTEND="noninteractive"

sudo apt install lsb-release wget
echo "deb http://repo.vitexsoftware.cz $(lsb_release -sc) main backports" | sudo tee /etc/apt/sources.list.d/vitexsoftware.list
sudo wget -O /etc/apt/trusted.gpg.d/vitexsoftware.gpg http://repo.vitexsoftware.cz/keyring.gpg

apt-get update
apt-get install -y devscripts dpkg-dev php-curl composer build-essential
mkdir -p /tmp/abraflexi-mailer
cp -r /vagrant/* /tmp/abraflexi-mailer
cd /tmp/abraflexi-mailer
debuild -us -uc
mkdir -p /vagrant/deb
mv /tmp/*.deb /vagrant/deb
cd /vagrant/deb
dpkg-scanpackages . /dev/null | gzip -9c > Packages.gz
echo "deb file:/vagrant/deb ./" > /etc/apt/sources.list.d/local.list
apt-get update

export DEBCONF_DEBUG="developer"
apt-get -y --allow-unauthenticated install abraflexi-client-config abraflexi-mailer
abraflexi-mailer-new2old
