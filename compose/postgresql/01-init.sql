  -- Moodle

      CREATE USER moodle
      WITH ENCRYPTED PASSWORD 'MoodlePassword123!';

      CREATE DATABASE moodle;

      GRANT ALL PRIVILEGES
      ON DATABASE moodle
      TO moodle;

      --------------------------------------------------

      -- Keycloak

      CREATE USER keycloak
      WITH ENCRYPTED PASSWORD 'KeycloakPassword123!';

      CREATE DATABASE keycloak;

      GRANT ALL PRIVILEGES
      ON DATABASE keycloak
      TO keycloak;

      --------------------------------------------------

      -- n8n

      CREATE USER n8n
      WITH ENCRYPTED PASSWORD 'N8NPassword123!';

      CREATE DATABASE n8n;

      GRANT ALL PRIVILEGES
      ON DATABASE n8n
      TO n8n;

      --------------------------------------------------

      -- Drupal

      CREATE USER drupal
      WITH ENCRYPTED PASSWORD 'DrupalPassword123!';

      CREATE DATABASE drupal;

      GRANT ALL PRIVILEGES
      ON DATABASE drupal
      TO drupal;
