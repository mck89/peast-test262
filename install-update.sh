REPODIR=test262

if [ ! -d "vendor" ]; then
    echo "-- Installing composer"
    composer install
else
    echo "-- Updating composer"
    composer update
fi
echo "-- DONE"

if [ ! -d "$REPODIR" ]; then
    echo "-- Cloning repository"
    git clone https://github.com/tc39/test262.git
else
    cd $REPODIR
    echo "-- Updating master"
    git checkout master
    git pull origin master
fi
echo "-- DONE"
