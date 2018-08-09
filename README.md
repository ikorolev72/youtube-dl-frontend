# frontend for youtube-dl. Download video/audio/subtitles and save data to AWS S3

## version 1.3 2018.08.09


## How to
### Install
```
cd 
git clone https://github.com/ikorolev72/youtube-dl-frontend
git clone https://github.com/ikorolev72/youtube_subtitles.git
cd youtube_subtitles
wget http://docs.aws.amazon.com/aws-sdk-php/v3/download/aws.phar

sudo apt-get -y install apache2 php libapache2-mod-php awscli
sudo service apache2 restart
cd 
cp -pR  youtube-dl-frontend /var/www
chown www-data /var/www/html/youtube-dl-frontend/logs/

mkdir /tmp/youtube-dl/
chmod 777 /tmp/youtube-dl/

# please insert aws credentials here
aws configure
# 
cd 
cp .aws/credentials youtube_subtitles/.aws/
chmod +r youtube_subtitles/.aws/credentials

```

##  Bugs
##  ------------




  Licensing
  ---------
	GNU

  Contacts
  --------

     o korolev-ia [at] yandex.ru
     o http://www.unixpin.com
