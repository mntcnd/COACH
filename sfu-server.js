const express = require('express');
const http = require('http');
const socketIo = require('socket.io');
const MediaServer = require('medooze-media-server');

const app = express();
const server = http.createServer(app);

const io = socketIo(server, {
    path: '/sfu-socket/socket.io',
    cors: { origin: '*', methods: ['GET', 'POST'] }
});

// Public IP (must be reachable from clients)
const PUBLIC_IP = '174.138.18.220';

const SFU_CONFIG = {
    ip: '0.0.0.0', 
    announcedIp: PUBLIC_IP,
    listenPort: process.env.PORT || 8080,
    rtcMinPort: 40000,
    rtcMaxPort: 49999
};

// Initialize Medooze
try {
    MediaServer.enableLog(true);
    MediaServer.enableDebug(true);
    MediaServer.setPortRange(SFU_CONFIG.rtcMinPort, SFU_CONFIG.rtcMaxPort);
    console.log('Medooze Media Server initialized successfully');
} catch (err) {
    console.error('Failed to initialize Medooze Media Server:', err.message);
    process.exit(1);
}

const rooms = new Map();
const socketPeers = new Map();

function getOrCreateRoom(forumId) {
    if (!rooms.has(forumId)) {
        try {
            const endpoint = MediaServer.createEndpoint(SFU_CONFIG);
            rooms.set(forumId, { endpoint, peers: new Map(), producers: new Map() });
            console.log(`Room created for forum ${forumId}`);
        } catch (err) {
            console.error(`Error creating room for forum ${forumId}:`, err.message);
            return null;
        }
    }
    return rooms.get(forumId);
}

io.on('connection', (socket) => {
    console.log(`Client connected [socketId:${socket.id}]`);

    socket.on('queryRoom', ({ appData }, callback) => {
        try {
            const { forumId } = appData || {};
            if (!forumId) throw new Error('forumId is required');
            const room = getOrCreateRoom(forumId);
            if (!room) throw new Error('Room not found');

            callback(null, { rtpCapabilities: MediaServer.getDefaultCapabilities() });
        } catch (err) {
            console.error('Error in queryRoom:', err.message);
            callback(err.message, null);
        }
    });

    socket.on('join', ({ peerName, rtpCapabilities, appData }, callback) => {
        try {
            const { forumId, displayName, profilePicture } = appData || {};
            if (!forumId) throw new Error('forumId is required');
            const room = getOrCreateRoom(forumId);
            if (!room) throw new Error('Room not found');

            socket.appData = { forumId, displayName, profilePicture };
            socket.peerName = peerName || `peer-${socket.id}`;

            room.peers.set(socket.id, { peerName: socket.peerName, socket });
            // ✅ FIX 1: Store rtpCapabilities for this peer
            socketPeers.set(socket.id, { peerName: socket.peerName, appData, rtpCapabilities, transports: [], producers: [], consumers: [] });

            for (const otherSocket of io.sockets.sockets.values()) {
                if (otherSocket.id !== socket.id && otherSocket.appData?.forumId === forumId) {
                    otherSocket.emit('notification', {
                        method: 'newpeer',
                        data: { peerName: socket.peerName, appData: socket.appData }
                    });
                }
            }

            const peersList = Array.from(room.peers.values()).map(p => ({
                name: p.peerName,
                appData: socketPeers.get(p.socket.id)?.appData || {}
            }));

            console.log(`Peer ${socket.peerName} joined room ${forumId}, total peers: ${peersList.length}`);
            callback(null, { peers: peersList });
        } catch (err) {
            console.error('Error in join:', err.message);
            callback(err.message, null);
        }
    });

    socket.on('createTransport', ({ direction }, callback) => {
    try {
        const { forumId } = socket.appData || {};
        if (!forumId) throw new Error('forumId is required');
        const room = getOrCreateRoom(forumId);
        if (!room) throw new Error('Room not found');

        console.log(`🔹 [${socket.id}] Creating transport, direction=${direction}`);
        
        const peer = socketPeers.get(socket.id);
        if (!peer || !peer.rtpCapabilities) {
            throw new Error('Peer or its RTP capabilities not found');
        }

        // --- CORRECTED METHOD for v0.148.0 ---
        // Pass a configuration OBJECT with a key 'rtpCapabilities'
        let transport = room.endpoint.createTransport({ 
            rtpCapabilities: peer.rtpCapabilities 
        });
        // --- END OF CORRECTED METHOD ---

        const ice = transport.getICEInfo();
        const dtls = transport.getDTLSInfo();

        if (!ice || !dtls) {
            throw new Error("Transport creation failed: No ICE/DTLS info returned. Check server logs and configuration.");
        }

        transport.appData = { direction, socketId: socket.id };
        peer.transports.push(transport);

        callback(null, {
            id: transport.id,
            ice,
            dtls
        });
    } catch (err) {
        console.error('❌ Error creating transport:', err.message);
        callback(err.message, null);
    }
});
    socket.on('connectTransport', ({ id, dtls }, callback) => {
        try {
            const peer = socketPeers.get(socket.id);
            if (!peer) throw new Error('Peer not found');
            const transport = peer.transports.find(t => t.id === id);
            if (!transport) throw new Error('Transport not found');

            transport.setRemoteDTLS(dtls);
            console.log(`Transport [${id}] connected for socket ${socket.id}`);
            callback(null);
        } catch (err) {
            console.error('Error connecting transport:', err.message);
            callback(err.message);
        }
    });

    socket.on('createProducer', ({ transportId, kind, rtpParameters, appData }, callback) => {
        try {
            const peer = socketPeers.get(socket.id);
            if (!peer) throw new Error('Peer not found');

            const transport = peer.transports.find(t => t.id === transportId);
            if (!transport) throw new Error('Transport not found');

            const producer = transport.produce({ kind, rtpParameters, appData });
            peer.producers.push(producer);

            const room = getOrCreateRoom(socket.appData.forumId);
            room.producers.set(producer.id, producer);

            for (const otherSocket of io.sockets.sockets.values()) {
                if (otherSocket.id !== socket.id && otherSocket.appData?.forumId === socket.appData.forumId) {
                    otherSocket.emit('notification', {
                        method: 'newproducer',
                        data: { id: producer.id, kind, rtpParameters, peerName: socket.peerName, appData }
                    });
                }
            }

            console.log(`Producer [${producer.id}, ${kind}] created for ${socket.peerName}`);
            callback(null, { id: producer.id });
        } catch (err) {
            console.error('Error creating producer:', err.message);
            callback(err.message, null);
        }
    });

    socket.on('createConsumer', ({ transportId, producerId, rtpParameters }, callback) => {
        try {
            const peer = socketPeers.get(socket.id);
            if (!peer) throw new Error('Peer not found');
            const transport = peer.transports.find(t => t.id === transportId);
            if (!transport) throw new Error('Transport not found');

            const room = getOrCreateRoom(socket.appData.forumId);
            const producer = room.producers.get(producerId);
            if (!producer) throw new Error('Producer not found');

            const consumer = transport.consume(producer, rtpParameters);
            peer.consumers.push(consumer);

            console.log(`Consumer [${consumer.id}] created for ${socket.peerName}`);
            callback(null, {
                id: consumer.id,
                producerId,
                kind: consumer.getMedia(),
                rtpParameters: consumer.getParameters()
            });
        } catch (err) {
            console.error('Error creating consumer:', err.message);
            callback(err.message, null);
        }
    });

    socket.on('resumeConsumer', ({ consumerId }, callback) => {
        try {
            const peer = socketPeers.get(socket.id);
            if (!peer) throw new Error('Peer not found');
            const consumer = peer.consumers.find(c => c.id === consumerId);
            if (!consumer) throw new Error('Consumer not found');
            consumer.resume();
            console.log(`Consumer [${consumerId}] resumed`);
            callback(null);
        } catch (err) {
            console.error('Error resuming consumer:', err.message);
            callback(err.message);
        }
    });

    socket.on('disconnect', () => {
        console.log(`Client disconnected [socketId:${socket.id}]`);
        const peer = socketPeers.get(socket.id);
        if (peer) {
            const { forumId } = socket.appData || {};
            const room = getOrCreateRoom(forumId);
            if (room) {
                peer.transports?.forEach(t => t.close());
                peer.producers?.forEach(p => {
                    p.close();
                    room.producers.delete(p.id);
                });
                peer.consumers?.forEach(c => c.close());
                room.peers.delete(socket.id);

                for (const otherSocket of io.sockets.sockets.values()) {
                    if (otherSocket.id !== socket.id && otherSocket.appData?.forumId === forumId) {
                        otherSocket.emit('notification', {
                            method: 'peerclosed',
                            data: { peerName: socket.peerName }
                        });
                    }
                }
            }
            socketPeers.delete(socket.id);
        }
    });
});

server.listen(SFU_CONFIG.listenPort, () => {
    console.log(`SFU signaling server running on port ${SFU_CONFIG.listenPort}`);
});

process.on('uncaughtException', (err) => {
    console.error('Uncaught Exception:', err.message);
    process.exit(1);
});
