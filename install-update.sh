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
    echo "-- Updating"
fi
cd $REPODIR
git fetch
git checkout main
git pull origin main
echo "-- DONE"
