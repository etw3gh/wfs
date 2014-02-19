RESTful API for wfs
===


Current backend url: wfs.openciti.ca
---

Can be hosted on Google Compute Engine or AWS


API Methods
---

register_user:

    register_user
        username
        password
        first
        last
        lat
        lng  

    http://wfs.openciti.ca/?method=register_user&username=myusername2&password=LA26235612356SHFGSANN&first=Joe&last=Blow&lat=49.0&lng=-78.0&full_response=true

    response:
    {"username":"myusername2","password":"LA26235612356SHFGSANN","first":"Joe","last":"Blow","lat":"49.0","lng":"-78.0","_id":{"$id":"530538d23a3cadf50223c709"},"response":"ok"}

    try again with same info:
    {"response":"duplicate user"}

    set full_response to 'false' (can just be omitted as this is the default setting)

    http://wfs.openciti.ca/?method=register_user&username=stevejobs2&password=LA26235612356SHFGSANN&first=Steve&last=Jobs&lat=49.0&lng=-78.0&full_response=false

    {"response":"ok"}



login_user:

    login_user
        username
        password

roll_dice:
    
    roll_dice
    incomplete....
