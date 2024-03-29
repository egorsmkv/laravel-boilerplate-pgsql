import zmq

context = zmq.Context()
socket = context.socket(zmq.REP)
socket.bind("tcp://*:5555")

print("Python ZeroMQ server started")

while True:
    # Wait for next request from client
    message = socket.recv()
    print("Received request: %s" % message)

    # Send reply back to client
    socket.send(b"World and the client says: %s" % message)
