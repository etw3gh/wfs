![Alt text](badman.jpg "badman.jpg")
RESTful API for wfs
===

(it is assumed that passwords will be md5 encoded by the application)

Current backend url: wfs.openciti.ca/ENDPOINT.PHP?method=METHOD&.....

Currently methods do not verify correctness of parameters sent.

ENDPOINTS --> METHODS ----> PARAMETERS
---

    admin.php
        register
                username            unique wfs username
                password            md5 encoded password
                first               name
                last                name
                full_response       true or false (choose false unless you want the user details returned)
        login
                password            md5 encoded password
                username            unique wfs username


    nearby.php
        nearby
                lat                 latitude
                lng                 longitude
                username            unique wfs username
                how_many            limit query to this many results
                restrict_categories true or false (if true non-business venues are not queried)


    checkin.php
        checkin
                id                  unique FourSquare venue id
                username            unique wfs username
        checkout
                id                  unique FourSquare venue id
                username            unique wfs username


    soldiers.php
        place
                id                  unique FourSquare venue id
                username            unique wfs username
                number              how many soldiers to put here
        pickup
                id                  unique FourSquare venue id
                username            unique wfs username

    TODO:
    attack.php
    stats.php
    other???.php


Example:
---
    http://wfs.openciti.ca/nearby.php?method=nearby&lat=43.656714493508&lng=-79.380351305008&how_many=1&username=stevejobs&restrict_categories=false
    {
        "response": "ok",
        "top_venues": {
            "result": [
                {
                    "venues": {
                        "id": "4ad8cd16f964a520c91421e3",
                        "name": "Yonge-Dundas Square",
                        "distance": 71,
                        "checkins": 38581
                    }
                }
            ]
            "ok": 1
        }
    }

