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
        latitude
        longitude
        username
        how_many

    http://wfs.openciti.ca?method=nearby_venues&lat=43.6572331&lng=-79.378499&username=stevejobs2&how_many=5

    {"response":"ok",
    "top5":

    {"result":
    [
        {"nearby":{"id":"4b02f140f964a520404b22e3",
        "name":"George Vari Engineering and Computing Centre",
        "distance":77,
        "checkinsCount":2867}},

        {"nearby":{"id":"4b4ce994f964a520f3c326e3",
        "name":"Victoria Building",
        "distance":99,
        "checkinsCount":2148}},

        {"nearby":{"id":"4d21f9a05c4ca1cdd1f9ad3d",
        "name":"Ryerson Square",
        "distance":106,
        "checkinsCount":1629}},

        {"nearby":{"id":"4adfbe44f964a5202d7d21e3",
        "name":"The Ram in the Rye",
        "distance":87,
        "checkinsCount":1508}},

        {"nearby":{"id":"4ae1b49ff964a520d78621e3",
        "name":"Student Campus Centre",
        "distance":79,
        "checkinsCount":1452}}],


    "ok":1}}



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
