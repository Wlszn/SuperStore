# CF600 reader

The Chafon CF600 is a USB desk reader. `scripts/RFID_Scanner.py` talks to it through its virtual COM port and sends every tag it reads to SuperStore (`POST /api/scans`). The self-checkout and Receive stock pages pick the tags up from there. No SDK is needed on the Pi or on the PC that runs the script.

## One-time reader setup (Windows)

The reader keeps its settings, so this is done once per reader.

1. Download the SDK as described in the teacher's instructions: on https://www.chafon.com/Download, open **UHF Desktop reader writer** and download **CF600 601 603 SDK.rar** (password `chafonsdk`). Extract it with 7-Zip. It is only needed for this setup; do not add it to the repository.
2. Plug in the reader and run `UHF Desk Reader SDK\Software V1.1.2\UHF_Desk.exe`. Select **Connect Reader**.
3. On the **Config** tab, select **Get**, set **Communication Interface** to **CDC_COM**, then select **Set** ("Settings Successful" appears at the bottom). Leave the work mode on **Answer Mode**: the script starts and stops the reading itself.
4. Unplug and replug the reader. Windows now lists it under *Ports (COM & LPT)* in Device Manager (for example `USB Serial Device (COM6)`); the Raspberry Pi shows it as `/dev/ttyACM0`.

To change the settings again, connect to the reader through its COM port in `UHF_Desk.exe`.

## Running the reader script

Start SuperStore first (`php -S 0.0.0.0:8000 -t public`), close `UHF_Desk.exe` (only one program can use the port), then run from the project folder.

On the Raspberry Pi (`python3-serial` is in the README install command):

    python3 scripts/RFID_Scanner.py --port /dev/ttyACM0

On Windows:

    pip install pyserial
    python scripts/RFID_Scanner.py --port COM6

Use the port shown in Device Manager (Windows) or by `ls /dev/ttyACM*` (Pi). `--url` sets the SuperStore address and defaults to `http://127.0.0.1:8000`. With tags on the reader the script prints `Sent 4 tag(s) to SuperStore.` about twice a second. If the Pi answers "Permission denied", run `sudo usermod -aG dialout $USER`, then log out and back in. Stop the script with Ctrl+C.

## How it works

- The script sends the reader one *start reading* command (CF600 frame `CF FF 00 01 ...`). The reader then streams one frame per tag read; the script takes the EPC out of each frame and, every half second, sends all the tags it saw together, up to 100 per request.
- If nothing arrives for 5 seconds, the start command is sent again (for example after the reader was reset). Ctrl+C sends *stop reading*, so the reader is left idle.
- While a tag lies on the reader it is reported about twice a second. The pages count each EPC once; a tag lifted off for more than 3 seconds and put back is shown as scanned again.

## Using it in SuperStore

To register a product type, open Add product, click the Barcode field and scan the product's barcode. To receive stock, open Receive stock, scan the same barcode (or choose the product), put all the new tagged items on the reader, then select Receive items: every new tag becomes one item.

At the self-checkout, put all the items on the reader: they are added together and grouped by product. Each EPC is counted once per basket.

The application links each existing EPC to an item. It does not rewrite RFID tag memory.

## Verified

On Windows (COM6), four tags on the reader were read at once and appeared together on the self-checkout. The Raspberry Pi (`/dev/ttyACM0`) still has to be tested with the reader in CDC_COM mode.
