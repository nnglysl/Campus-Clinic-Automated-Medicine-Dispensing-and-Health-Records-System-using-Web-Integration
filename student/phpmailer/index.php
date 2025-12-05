<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Contact Form</title>
  <style>
    body{
    background-color: aliceblue;}
    .contact {
      position: absolute;
      left: 50%;
      top: 50%;
      transform: translate(-50%, -50%);
      transition: 1s;
    }

    .contact .input-box input,
    textarea {
      width: 100%;
      height: 35px;
      padding: 20px;
      letter-spacing: 1px;
      outline: none;
      border: 1px solid rgba(0, 0, 0, 0.61);
      /* border: 2px solid rgb(238, 224, 243); */
      border-radius: 5px;
      margin: 0;
      margin-bottom: 10px;
      box-shadow: 0 1px rgba(0, 0, 0, 0.233);
      resize: none;
      box-sizing: border-box;
    }

    .contact .input-box input:focus {
      border: 1px solid rgb(0, 128, 255);
    }

    .contact .input-box textarea:focus {
      border: 1px solid dodgerblue;
    }

    .contact .input-box textarea {
      height: 100%;
    }

    ::placeholder {
      color: rgba(0, 0, 0, 0.521);
      letter-spacing: 0;
      font-size: 15px;
    }

    .contact .submit-btn input {
      width: 100%;
      height: 35px;
      border: none;
      /* background-color: rgba(17, 135, 253, 0.952); */
      border: 1px solid rgb(0, 128, 255);
      ;
      background-color: transparent;
      border-radius: 3px;
      color: rgb(0, 128, 255);
      font-size: 20px;
      font-family: serif;
      padding: 0 8px;
      box-shadow: 0 3px 3px rgba(0, 0, 0, 0.116);
      border-radius: 5px;
      outline: none;
      cursor: pointer;
      transition: 0.3s ease-in-out;
    }

    .contact .submit-btn input:hover {
      background-color: rgb(0, 128, 255);
      color: white;
    }

    h2 {
      color: rgb(0, 128, 255);
      text-align: center;
      font-size: 30px;
    }
  </style>
</head>

<body>
  <div class="contact">
    <h2>Contact Now</h2>
    <form action="mail.php" method="post">
      <div class="input-box">
        <input type="text" name="name" placeholder="Your name">
      </div>
      <div class="input-box">
        <input type="email" name="email" placeholder="Email Address">
      </div>
      <div class="input-box">
        <textarea name="message" cols="25" rows="7" placeholder="Type your message"></textarea>
      </div>
      <div class="submit-btn">
        <input type="submit" name="send" value="Send Message">
      </div>
    </form>
  </div>
</body>

</html>