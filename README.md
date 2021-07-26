# test-ideaware
En este reporsitorio se encuentra el desarrollo de la prueba tecnica propuesta por Ideaware para la posicion de Backend Developer- PHP

Podran encontrar las configuraciones de base de datos y de Aweber en la ruta back/enviroment.ini

Se debe generar el authorization_code (yo usaba este link https://auth.aweber.com/oauth2/authorize?response_type=code&client_id=einqz7etXmPuxdVtJ1gUjhqCtoTBcLbg&redirect_uri=https://localhost&scope=subscriber.write%20subscriber.read%20account.read&state=20210726090000) luego ya cuendo se obtenga el codigo se debe poner en el archivo enviroment.ini, ya con eso se generar el token y se refresca en cada ejecución de codigo.

Gracias!
