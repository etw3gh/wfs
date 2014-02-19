RESTful API for wfs
===


Current url: wfs.openciti.ca
---

Can be hosted on Google Compute Engine or AWS
Current backend url: http://wfs.openciti.ca

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

    http://wfs.openciti.ca/?method=register_user&username=myusername2&password=LA26235612356SHFGSANN&first=Joe&last=Blow&lat=49.0&lng=-78.0

    response:
    {"username":"myusername2","password":"LA26235612356SHFGSANN","first":"Joe","last":"Blow","lat":"49.0","lng":"-78.0","_id":{"$id":"530538d23a3cadf50223c709"},"response":"ok"}

    try again with same info:
    {"response":"duplicate user"}

login_user:

    login_user
        username
        password

roll_dice:
    
    roll_dice
    incomplete....
