# Alleen gebruiken als je Apache i.p.v. nginx draait.
# Voeg ook toe aan /etc/apache2/ports.conf:  Listen 127.0.0.1:8081
<VirtualHost 127.0.0.1:8081>
    DocumentRoot /var/www/bugbusters-backend/public

    <Directory /var/www/bugbusters-backend/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/campusconnect-error.log
    CustomLog ${APACHE_LOG_DIR}/campusconnect-access.log combined
</VirtualHost>
