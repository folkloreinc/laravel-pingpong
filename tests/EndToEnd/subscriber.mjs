// A Socket.IO client for the end-to-end tests, with socket.io-client from the Pingpong checkout.
// It prints its socket id, reads the channel's signature on stdin, subscribes, prints the ack,
// then prints each message it receives, one JSON per line.
//
// Usage: node subscriber.mjs <url> <project> <key> <channel>
import { createRequire } from 'node:module';
import { createInterface } from 'node:readline';

const [url, project, key, channel] = process.argv.slice(2);
const require = createRequire(`${process.env.PINGPONG_E2E_CHECKOUT}/packages/client/package.json`);
const { io } = require('socket.io-client');

const print = (value) => process.stdout.write(`${JSON.stringify(value)}\n`);
const socket = io(`${url}/projects/${project}`, { auth: { key }, transports: ['websocket'] });
socket.on('connect_error', (error) => {
    print({ error: error.message });
    process.exit(1);
});
socket.on('message', (message) => print({ message }));
socket.once('connect', () => {
    print({ socketId: socket.id });
    createInterface({ input: process.stdin }).once('line', async (signature) => {
        print({ ack: await socket.emitWithAck('subscribe', { channel, signature: signature.trim() }) });
    });
});
setTimeout(() => process.exit(2), 15_000);
