<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>SuperStore - Add Customer</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 40px;
        }

        .container {
            width: 400px;
            margin: auto;
            background-color: white;
            padding: 30px;
            border-radius: 10px;
        }

        h1 {
            text-align: center;
        }

        label {
            display: block;
            margin-top: 15px;
            margin-bottom: 5px;
        }

        input {
            width: 100%;
            padding: 10px;
            box-sizing: border-box;
        }

        button {
            width: 100%;
            margin-top: 20px;
            padding: 12px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        button:hover {
            background-color: #0056b3;
        }

        #message {
            margin-top: 20px;
            text-align: center;
            padding: 10px;
        }
    </style>
</head>

<body>

<div class="container">

    <h1>Add Customer</h1>

    <form id="customerForm">

        <label for="name">Customer Name</label>
        <input
            type="text"
            id="name"
            name="name"
            maxlength="50"
            required
        >

        <label for="email">Email Address</label>
        <input
            type="email"
            id="email"
            name="email"
            maxlength="100"
            required
        >

        <label for="phone">Phone Number</label>
        <input
            type="tel"
            id="phone"
            name="phone"
            maxlength="15"
        >

        <label for="address">Address</label>
        <input
            type="text"
            id="address"
            name="address"
            maxlength="255"
        >

        <button type="submit">
            Add Customer
        </button>

    </form>

    <div id="message"></div>

</div>

<script>
    document.getElementById("customerForm").addEventListener("submit", async function(event) {

        event.preventDefault();

        const form = event.target;
        const message = document.getElementById("message");

        const formData = new FormData(form);

        try {

            const response = await fetch("../index.php?action=addCustomer", {
                method: "POST",
                body: formData
            });

            const data = await response.json();

            message.textContent = data.message;

            if (data.status === "success") {

                message.style.color = "green";
                form.reset();

            } else {

                message.style.color = "red";
            }

        } catch (error) {

            message.textContent = "An error occurred while adding the customer.";
            message.style.color = "red";
        }
    });
</script>
</body>
</html>