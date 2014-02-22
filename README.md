RESTful API for wfs
===


Current backend url: wfs.openciti.ca
---

Can be hosted on Google Compute Engine or AWS


API Methods
---

(it is assumed that passwords will be md5 encoded by the application)

register_user:

    register_user
        username
        password
        first
        last

    http://wfs.openciti.ca?method=register_user&username=myusername2&password=LA26235612356SHFGSANN&first=Joe&last=Blow&full_response=true

    response:
    {"username":"myusername2","password":"LA26235612356SHFGSANN","first":"Joe","last":"Blow","_id":{"$id":"530538d23a3cadf50223c709"},"response":"ok"}

    try again with same info:
    {"response":"duplicate user"}

    set full_response to 'false'

    http://wfs.openciti.ca?method=register_user&username=stevejobs2&password=LA26235612356SHFGSANN&first=Steve&last=Jobs&full_response=false

    {"response":"ok"}



login_user:

    login_user
        username
        password


nearby_venues (return from top of sorted list according to 'how_many'):

    nearby_venues
        lat
        lng
        username
        how_many

    http://wfs.openciti.ca/?method=nearby_venues&lat=43.656714493508&lng=-79.380351305008&how_many=5&username=stevejobs

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
                },
                {
                    "venues": {
                        "id": "4adbbae6f964a520402a21e3",
                        "name": "Cineplex Cinemas Yonge-Dundas",
                        "distance": 0,
                        "checkins": 23705
                    }
                },
                {
                    "venues": {
                        "id": "4ad7797cf964a520170b21e3",
                        "name": "Ryerson University",
                        "distance": 117,
                        "checkins": 8916
                    }
                },
                {
                    "venues": {
                        "id": "4ad9ffbbf964a520091d21e3",
                        "name": "Jack Astor's Bar & Grill",
                        "distance": 30,
                        "checkins": 8800
                    }
                },
                {
                    "venues": {
                        "id": "4a6eee3ef964a52016d51fe3",
                        "name": "GoodLife Fitness",
                        "distance": 88,
                        "checkins": 7069
                    }
                }
            ],
            "ok": 1
        }
    }


WarFareSquare Checkin / Checkout

    wfs_checkin
        username
        id

    wfs_checkout (assumes user is only checked into 1 place)
        username



Game Logic Methods
---

roll_dice:
    
    roll_dice
    incomplete....
