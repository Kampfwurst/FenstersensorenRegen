<?php

class WindowSensor extends IPSModule {

    public function Create() {
        // Diese Zeile nicht löschen
        parent::Create();

        // Moduleigenschaften registrieren
        $this->RegisterPropertyInteger("Sensor1ID", 0);
        $this->RegisterPropertyInteger("Sensor2ID", 0);
        $this->RegisterPropertyInteger("RainStatusID", 0);
        $this->RegisterPropertyInteger("RaffstoreStatusID", 0);
        $this->RegisterPropertyInteger("WindSpeedID", 0);
        $this->RegisterPropertyFloat("WindThreshold", 0.0);
        $this->RegisterPropertyString("WindowName", "");
        $this->RegisterPropertyInteger("AlexaID", 0);

        // Variablenprofile erstellen
        if (!IPS_VariableProfileExists("WS.Status")) {
            IPS_CreateVariableProfile("WS.Status", 1);
            IPS_SetVariableProfileAssociation("WS.Status", 0, "Geschlossen", "", 0x00FF00);
            IPS_SetVariableProfileAssociation("WS.Status", 1, "Gekippt", "", 0xFFFF00);
            IPS_SetVariableProfileAssociation("WS.Status", 2, "Offen", "", 0xFF0000);
        }

        if (!IPS_VariableProfileExists("WS.WindSpeed")) {
            IPS_CreateVariableProfile("WS.WindSpeed", 2); // Profil für Windgeschwindigkeit, Typ Float
            IPS_SetVariableProfileText("WS.WindSpeed", "", " m/s");
        }

        // Variablen registrieren
        $this->RegisterVariableInteger("WindowStatus", "Fensterstatus", "WS.Status", 0);
        $this->RegisterVariableFloat("RaffstorePosition", "Raffstore Position", "~Intensity.100", 1);
        $this->RegisterVariableFloat("WindSpeed", "Windgeschwindigkeit", "WS.WindSpeed", 2);
        $this->RegisterVariableBoolean("IsRaining", "Regenstatus", "~Switch", 3);

        // Timer registrieren
        $this->RegisterTimer("CheckConditions", 0, 'WS_CheckConditions($_IPS[\'TARGET\']);');
        $this->RegisterTimer("CloseRaffstore", 0, 'WS_CloseRaffstore($_IPS[\'TARGET\']);');
    }

    public function ApplyChanges() {
        // Diese Zeile nicht löschen
        parent::ApplyChanges();

        // Timer setzen
        $this->SetTimerInterval("CheckConditions", 60000); // Jede Minute
    }

    public function CheckConditions() {
        $sensor1ID = $this->ReadPropertyInteger("Sensor1ID");
        $sensor2ID = $this->ReadPropertyInteger("Sensor2ID");
        $rainStatusID = $this->ReadPropertyInteger("RainStatusID");
        $raffstoreStatusID = $this->ReadPropertyInteger("RaffstoreStatusID");
        $windSpeedID = $this->ReadPropertyInteger("WindSpeedID");
        $windThreshold = $this->ReadPropertyFloat("WindThreshold");
        $windowName = $this->ReadPropertyString("WindowName");
        $alexaID = $this->ReadPropertyInteger("AlexaID");

        $isRaining = GetValueBoolean($rainStatusID);
        $windSpeed = GetValueFloat($windSpeedID);
        $raffstorePosition = GetValueFloat($raffstoreStatusID);
        $sensor1Status = GetValueBoolean($sensor1ID);
        $sensor2Status = GetValueBoolean($sensor2ID);

        // Fensterstatus bestimmen
        $windowStatus = 0; // Geschlossen
        if ($sensor2Status) {
            $windowStatus = 1; // Gekippt
        }
        if ($sensor1Status) {
            $windowStatus = 2; // Offen
        }

        // Fensterstatus speichern
        SetValueInteger($this->GetIDForIdent("WindowStatus"), $windowStatus);

        // Bedingung für Alexa-Warnung prüfen
        if ($isRaining && $windowStatus > 0 && $raffstorePosition < 100) {
            $message = "Achtung. Das Fenster $windowName ist ";
            if ($windowStatus == 1) {
                $message .= "gekippt";
            } else if ($windowStatus == 2) {
                $message .= "offen";
            }
            $message .= " und es regnet.";
            $this->SendAlexaMessage($alexaID, $message);
        }

        // Bedingung für das Schließen der Raffstores prüfen
        if ($isRaining && $windSpeed > $windThreshold) {
            $this->SetTimerInterval("CloseRaffstore", 300000); // 5 Minuten
        }
    }

    public function CloseRaffstore() {
        $raffstoreStatusID = $this->ReadPropertyInteger("RaffstoreStatusID");
        SetValueFloat($raffstoreStatusID, 100.0);

        $alexaID = $this->ReadPropertyInteger("AlexaID");
        $message = "Der Raffstore wurde wegen starkem Wind und Regen geschlossen.";
        $this->SendAlexaMessage($alexaID, $message);
    }

    private function SendAlexaMessage($alexaID, $message) {
        ECHOREMOTE_SetVolume($alexaID, 60);
        IPS_Sleep(1000);
        ECHOREMOTE_TextToSpeech($alexaID, $message);
        IPS_Sleep(10000);
        ECHOREMOTE_SetVolume($alexaID, 20);
    }
}
?>
