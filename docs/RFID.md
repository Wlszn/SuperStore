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

To register a product type, open Add product, click the Barcode field and scan the product's barcode. To receive stock, open Receive stock, scan the same barcode (or choose the product), put all the new tagged items on the reader, then select Receive items: every new tag becomes one item.

At the self-checkout the reader starts by itself. Put all the items on it: they are added together and grouped by product. Each EPC is counted once per basket. The bridge forwards batches of up to 100 tags.

The application links each existing EPC to an item. It does not rewrite RFID tag memory.

Verified on this computer: Windows SDK loads and USB enumeration runs. No reader was connected, so physical tag reads and Raspberry Pi execution remain unverified.
