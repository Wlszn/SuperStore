# CF600 reader

Start SuperStore on port 8000. Connect the reader by USB and close the vendor reader application.

On Windows, run from the project folder:

    python scripts/rfid.py --sdk "C:\Users\apiiu\Downloads\CF600+601+603+SDK\UHF Desk Reader SDK"

On a Raspberry Pi, copy the SDK folder onto the Pi and pass its location:

    python3 scripts/rfid.py --sdk "/path/to/UHF Desk Reader SDK"

The bridge selects the SDK library for the operating system and CPU. Linux USB access depends on device permissions. Use --list to check detection. Use --read-only --seconds 10 to read tags without sending them to the app.

For a reader configured in serial (CDC-COM) mode, use the serial bridge instead:

    python3 scripts/RFID_Scanner.py --port /dev/ttyUSB0 --url http://127.0.0.1:8000

On Windows replace the port with the reader's COM port and use `python`. If SuperStore runs on a different machine, pass its base URL. Restart the reader script after updating it. When using the SDK bridge, omit `--read-only` for checkout.

For registration, open Add product, focus Barcode and scan the card. Select Read EPC from reader and present one RFID tag. Save the product, then receive stock.

For checkout, select Start RFID reader before placing products on the reader. Matching products are added automatically, and the scan field shows the latest EPC when it is clear. Each EPC is counted once per basket. The bridge forwards batches of up to 100 tags.

The application assigns the existing EPC to a product record. It does not rewrite RFID tag memory.

Verified on this computer: Windows SDK loads and USB enumeration runs. No reader was connected, so physical tag reads and Raspberry Pi execution remain unverified.
