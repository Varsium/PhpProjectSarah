<?php

use Cassandra\Date;

//variables for Function Timeslots
$duration = 30;
$cleanup = 0;
$start = "08:30";


$mysqli = new mysqli('localhost', 'root', '', 'sarahbooking');
if (isset($_GET['date'])) {
    //Variables
    $date = $_GET['date'];
    $bookings = array();
    $dictionaryActions = array(); //this one is used as an HashMap (its an array in an array)

    //Sql data from db in variables
    $stmt = $mysqli->prepare("select * from bookings where datum =?");
    $stmt->bind_param('s', $date);

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $i = 0;
            while ($row = $result->fetch_assoc()) {
                $temp = substr($row["tijdslot"], 0, 5);
                $bookings[$i] = ['timeslot' => $temp, "Actionid" => $row["Werkdaad"]];
                $i++;
            }
            $stmt->close();
        }
    }

    //Sql data from db in variables
    $stmt = $mysqli->prepare("select * from werkdaden");
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $dictionaryActions[] = ["Id" => $row["ActieId"], "behandeling" => $row["Actie"], "prijs" => $row["prijs"], "time" => $row["duratie"]];
            }
            $stmt->close();
        }
    }
}


//When booking is submitted
if (isset($_POST['submit'])) {
    //variables
    $name = $_POST['naam'];
    $Familyname = $_POST['Achternaam'];
    $email = $_POST['email'];
    $timeslot = $_POST['tijdslot'];
    $number = $_POST['nummer'];
    $werkdaad = 0;

    //Created a "hashmap" instead of different arrays. so i need to "foreach" each part seperate. => Linking "id" to "Behandeling"
    foreach ($dictionaryActions as $dAction) {
        if ($dAction['behandeling'] == $_POST['behandeling']) {
            $werkdaad = $dAction['Id'];
            break;
        }
    }

    //Sql data from db in variables and insert into DB
    $stmt = $mysqli->prepare("select * from bookings where datum =? and tijdslot=?");
    $stmt->bind_param('ss', $date, $timeslot);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $msg = "<div class='alert alert-danger'>Dit uur was al geboekt!</div>";
        } else {

            $stmt = $mysqli->prepare("INSERT INTO bookings (Voornaam,Achternaam,Emailadres,nummer,werkdaad,Datum,tijdslot) VALUES (?,?,?,?,?,?,?)");
            $stmt->bind_param('ssssiss', $name, $Familyname, $email, $number, $werkdaad, $date, $timeslot);
            $stmt->execute();
            $msg = "<div class='alert alert-success'>Uw boeking werd goed ontvangen</div>";
            $bookings[] = $timeslot;
            $stmt->close();
            $mysqli->close();
        }
    }

}


//varable $end checking if it is saturday or not.
if (substr(date('D/m/Y', strtotime($date)), 0, 3) == 'Sat') {
    $end = "12:30";
} else {
    $end = "18:30";
}

//Function to create the different timeslots.
function Timeslots($duration, $cleanup, $start, $end)
{
    try {
        $start = new DateTime(($start));
    } catch (Exception $e) {
    }
    $end = new DateTime(($end));
    $interval = new DateInterval("PT" . $duration . "M");
    $cleanupinterval = new DateInterval("PT" . $cleanup . "M");
    $slots = array();

    for ($intstart = $start; $intstart < $end; $intstart->add($interval)->add($cleanupinterval)) {
        $endPeriod = clone $intstart;
        $endPeriod->add($interval);
        if ($endPeriod > $end) {
            break;
        }
        $slots[] = $intstart->format("H:i");
    }
    return $slots;
}

//Fucntion to change the time to Minutes
function time_to_Minutes($time)
{
    $timeArr = explode(':', $time);
    $decTime = ($timeArr[0] * 60) + ($timeArr[1]) + ($timeArr[2] / 60);

    return $decTime;
}

//Returns value that "skips" the timeslots that are needed.
function BookingTimeBlocks($dictionaryActions, $ActionId, $duration)
{
    foreach ($dictionaryActions as $Dact) {
        if ($Dact["Id"] == $ActionId) {
            $time = $Dact["time"];
            return (time_to_Minutes($time) / $duration) - 1;
        }
    }
}

//This is a function to check if the Correct Timeslot is linked with the correct Booking.
function BookedNotBooked($Timeslot, $TimeBooked)
{
    foreach ($TimeBooked as $booking) {
        $booking['timeslot'];
        if ($Timeslot == $booking['timeslot']) {
            $BookingAction = $booking['Actionid'];
            return $BookingAction;
        }
    }
}


?>
<!doctype html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css"
          integrity="sha384-BVYiiSIFeK1dGmJRAkycuHAHRg32OmUcww7on3RYdg4Va+PmSTsz/K68vbdEjh4u" crossorigin="anonymous">
    <link rel="stylesheet" href="/css/NogInteVullen.css">  <!-- Nog css aanpassen naar gewenste stijl-->
</head>

<body>
<div class="container">
    <h1 class="text-center">boeken op <?php echo date('d/m/Y', strtotime($date)); ?></h1>
    <form method="post" action="BookingAgenda.php">
        <button type="submit" name="back">Terug</button>
    </form>
    <hr>
    <div class="row">
        <?php
        $timeslots = timeslots($duration, $cleanup, $start, $end);
        for ($i = 0;
        $i < sizeof($timeslots);
        $i++) {
        ?>
        <div class="col-md-2">
            <?php if (BookedNotBooked($timeslots[$i], $bookings) != 0)
            {
            ?>
            <button class="btn btn-danger"><?php echo $timeslots[$i]; ?></button>
        </div>
        <?php $i += BookingTimeBlocks($dictionaryActions, BookedNotBooked($timeslots[$i], $bookings), $duration); // skip the occoupied timeslots with this function.
        } else { ?>
        <button name="bookingslot" class="btn btn-succes book"
                data-timeslot="<?php echo $timeslots[$i]; ?>"><?php echo $timeslots[$i]; ?></button>
    </div>
    <?php
    }
    }
    ?>

    <div class="col-md-6 col-md-offset-3">
        <?php echo isset($msg) ? $msg : ''; ?>
        <!-- Modal -->
        <div id="myModal" class="modal fade" role="dialog">
            <div class="modal-dialog">
                <!-- Modal content-->
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                        <h4 class="modal-title">Boeken<span id="slot"></span></h4>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-12">
                                <form action="" method="post">
                                    <div class="form-group">
                                        <label for="">Tijdslot</label>
                                        <input required type="text" readonly name="tijdslot" id="tijdslot"
                                               content="<?php echo $timeslots[$i]; ?>">
                                    </div>
                                    <div class="form-group">
                                        <label for="">Naam</label>
                                        <input required type="text" name="naam" class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label for="">Achternaam</label>
                                        <input required type="text" name="Achternaam" class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label for="">Email</label>
                                        <input required type="email" name="email" class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label for="">Gsm-nummer</label>
                                        <input required type="tel" name="nummer" class="form-control">
                                    </div>
                                    <div class="form-group">
                                        <label for="">selecteer je behandeling</label>
                                        <select required name="behandeling" class="form-control">
                                            <?php foreach ($dictionaryActions as $Dact) { ?>
                                                <option value="<?php echo $Dact['behandeling'] ?>"><?php echo $Dact['behandeling'] ?></option>
                                                ";
                                            <?php }
                                            ?>
                                        </select>
                                    </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" name="submit" class="btn btn-primary">Bevestig afspraak</button>
                        <button type="button" class="btn btn-default" data-dismiss="modal">Sluiten</button>
                    </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"
        integrity="sha384-Tc5IQib027qvyjSMfHjOMaLkfuWVxZxUPnCJA7l2mCWNIpG9mGCD8wGNIcPD7Txa"
        crossorigin="anonymous"></script>

<script>
    $(".book").click(function () {
        var timeslot = $(this).attr('data-timeslot');
        $("#slot").html(timeslot);
        $("#tijdslot").val(timeslot);
        $("#myModal").modal("show");
    })
</script>
<script>
    $('#myModal').on('hidden.bs.modal', function () {
        location.reload();
    })
</script>
</body>
</html>
