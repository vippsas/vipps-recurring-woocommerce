# Running e2e tests through WSL2

## With remote Selenium Server

No docs here yet.

## With X-Server

Install X-Server on Windows and Selenium-webdriver and Google Chrome(or Firefox and Geckodriver) version 80 on WSL2.

If you are using Chrome you need to install exactly version 80, because WP's e2e library has an outdated version of chromedriver.

```
wget "https://dl.google.com/linux/chrome/deb/pool/main/g/google-chrome-stable/google-chrome-stable_80.0.3987.163-1_amd64.deb"
sudo dpkg -i google-chrome-stable_80.0.3987.163-1_amd64.deb
```

Start X-Server on Windows via the following command:

```
"C:\Program Files\VcXsrv\vcxsrv.exe" :0 -ac -terminate -lesspointer -multiwindow -clipboard -wgl -dpi auto
```

Now, export DISPLAY in WSL2:

```
export DISPLAY=$(ip route | awk '{print $3; exit}'):0
```

# On Mac or Linux

Install `selenium-webdriver`

# Running the tests

```
npm run e2e-test
```
