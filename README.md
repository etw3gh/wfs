![Alt text](badman.jpg "badman.jpg")
RESTful API for wfs
===

(passwords should be md5 encoded by the application)

url: wfs.openciti.ca/ENDPOINT.PHP?method=METHOD&.....

Currently methods do not verify correctness of parameters sent.


---

    admin.php
        register
                username            unique wfs username
                password            md5 encoded password
                first               name
                last                name
        login
                password            md5 encoded password
                username            unique wfs username


    nearby.php
        nearby
                lat                 latitude
                lng                 longitude
                how_many            limit query to this many results
                restrict            true or false (if true non-business venues are not queried)
                radius              in metres

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
    http://wfs.openciti.ca/nearby.php?method=nearby&lat=43.65&lng=-79.38&how_many=1&restrict=false
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

