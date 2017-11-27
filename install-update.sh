REPODIR=test262

if [ ! -d "$REPODIR" ]; then
    echo "-- Cloning repository"
    git clone https://github.com/tc39/test262.git
    echo "-- DONE"
fi

cd $REPODIR

echo "-- Updating master"
git checkout master
git pull origin master
echo "-- DONE"