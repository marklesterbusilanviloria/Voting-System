# Deploy iVOTE CS on Railway

1. Upload everything in this folder to a new GitHub repo.
2. railway.app -> New Project -> Deploy from GitHub repo -> pick the repo.
3. In the same project: + New -> Database -> Add MySQL.
4. Open your web service -> Variables -> New Variable:
   Name:  MYSQL_URL
   Value: ${{MySQL.MYSQL_URL}}      (use your MySQL service's name if you renamed it)
5. Settings -> Networking -> Generate Domain. Open the link.

The tables and data are created automatically on the first visit (from ivote_cs.sql).
Admin login: ADM-9901 / Passwordnije  -> change this password after you log in.
