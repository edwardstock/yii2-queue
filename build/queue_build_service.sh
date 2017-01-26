#!/usr/bin/env bash

function usage () {
	cat << EOF
Usage: $(basename "$0") [ install | remove ]  [ /yii/root/directory ] [ --debug ]
	Example with debug:                 ./vendor/bin/$(basename "$0") install . --debug
	Example without debug:              ./vendor/bin/$(basename "$0") install .
	Example with custom app directory:  ./vendor/bin/$(basename "$0") install /var/www/myshop

Files that will be created after service generation:
	/etc/init.d/yii2queue (service startup command)
	/etc/yii2queue/queue.conf (queue config file)

EOF
}

function remove () {
	rm -rf /etc/init.d/yii2queue
	echo "Successfully removed!"
}


if [ "$1" == "--help" ] || [ "$1" == "-h" ]
then
	usage;
	exit 0
fi

if [ $EUID != 0 ]; then
	sudo "$0" "$@"
	exit $?
fi

if [ "$1" != "remove" ] && [ "$1" != "install" ];
then
	echo "Please use \"install\" or \"remove\""
	usage;
	exit 1
fi

if [ "$1" == "remove" ];
then
	remove;
	exit 0
fi

if [ "$2" == "" ];
then
	echo "App root directory required"
	usage;
	exit 1
fi

APP_PATH=$(readlink -f "$2")
PACKAGE_PATH=$APP_PATH/vendor/edwardstock/yii2-queue
BUILD_PATH=$PACKAGE_PATH/build
CONFIG_FILE="/etc/yii2queue/queue.conf"
BUILD_ARGS="${BUILD_PATH}/build.xml -Denv.dir=${APP_PATH} -Denv.config=${CONFIG_FILE}"
PHING="${BUILD_PATH}/phing"

if [ "$3" == "--debug" ];
then
	BUILD_ARGS="${BUILD_ARGS} -Denv.debug=true"
fi

chmod +x $PHING
$PHING -f $BUILD_ARGS make
chmod +x $PACKAGE_PATH/yii2queue
cd /etc/init.d && ln -f -s $PACKAGE_PATH/yii2queue yii2queue && chmod +x yii2queue
mkdir -p /etc/yii2queue
cp --update $BUILD_PATH/example.conf $CONFIG_FILE

exit 0
